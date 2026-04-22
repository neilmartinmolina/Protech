<?php
class UserService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /** Get user by ID */
    public function getById(int $userId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT userId, first_name, last_name, username, email,
                   role, seller_status, avatar_path, store_name
            FROM users WHERE userId = ? LIMIT 1
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    /** Update basic profile */
    public function updateProfile(int $userId, string $firstName, string $lastName, string $username, ?string $avatarPath = null): array
    {
        $stmt = $this->conn->prepare(
            'UPDATE users SET first_name=?, last_name=?, username=?, avatar_path=? WHERE userId=?'
        );
        $stmt->bind_param('ssssi', $firstName, $lastName, $username, $avatarPath, $userId);
        $success = $stmt->execute();
        $stmt->close();

        return [
            'success' => $success,
            'message' => $success ? 'Profile updated.' : 'Profile update failed.'
        ];
    }

    /** Change email with password confirmation */
    public function changeEmail(int $userId, string $newEmail, string $password): array
    {
        // Verify password
        $stmt = $this->conn->prepare('SELECT password_hash FROM users WHERE userId = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row   = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            return ['success' => false, 'message' => 'Password is incorrect.'];
        }

        // Update
        $stmt = $this->conn->prepare('UPDATE users SET email = ? WHERE userId = ?');
        $stmt->bind_param('si', $newEmail, $userId);
        $success = $stmt->execute();
        $stmt->close();

        return $success
            ? ['success' => true, 'message' => 'Email updated successfully.']
            : ['success' => false, 'message' => 'Failed to update email.'];
    }

    /** Change password */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $stmt = $this->conn->prepare('SELECT password_hash FROM users WHERE userId = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row   = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt    = $this->conn->prepare('UPDATE users SET password_hash = ?, temp_password = NULL WHERE userId = ?');
        $stmt->bind_param('si', $newHash, $userId);
        $success = $stmt->execute();
        $stmt->close();

        return $success
            ? ['success' => true, 'message' => 'Password changed successfully.']
            : ['success' => false, 'message' => 'Password change failed.'];
    }

    /** Address methods (with transactions) */
    public function addAddress(int $userId, array $data): array
    {
        $this->conn->begin_transaction();
        try {
            if (!empty($data['set_default'])) {
                $stmt = $this->conn->prepare('UPDATE user_addresses SET is_default = 0 WHERE userId = ?');
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();
            }

            $stmt = $this->conn->prepare("
                INSERT INTO user_addresses
                (userId, recipient_name, phone, label, street, barangay, city, province, zip, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $defaultFlag = !empty($data['set_default']) ? 1 : 0;
            $stmt->bind_param(
                'issssssssi',
                $userId,
                $data['recipient_name'],
                $data['phone'],
                $data['label'] ?? '',
                $data['street'],
                $data['barangay'],
                $data['city'],
                $data['province'] ?? '',
                $data['zip'] ?? '',
                $defaultFlag
            );
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Address added.'];
        } catch (Throwable $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteAddress(int $userId, int $addressId): array
    {
        $stmt = $this->conn->prepare('DELETE FROM user_addresses WHERE userAddressId = ? AND userId = ?');
        $stmt->bind_param('ii', $addressId, $userId);
        $success = $stmt->execute();
        $stmt->close();

        return $success
            ? ['success' => true, 'message' => 'Address deleted.']
            : ['success' => false, 'message' => 'Failed to delete address.'];
    }

    public function setDefaultAddress(int $userId, int $addressId): array
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare('UPDATE user_addresses SET is_default = 0 WHERE userId = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->conn->prepare('UPDATE user_addresses SET is_default = 1 WHERE userAddressId = ? AND userId = ?');
            $stmt->bind_param('ii', $addressId, $userId);
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Default address set.'];
        } catch (Throwable $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function addPaymentMethod(int $userId, array $data): array
    {
        $this->conn->begin_transaction();
        try {
            if (!empty($data['set_default'])) {
                $stmt = $this->conn->prepare('UPDATE user_payment_methods SET is_default = 0 WHERE userId = ?');
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();
            }

            $stmt = $this->conn->prepare("
                INSERT INTO user_payment_methods
                (userId, type, label, gcash_name, gcash_number, is_default)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $defaultFlag = !empty($data['set_default']) ? 1 : 0;
            $gcashNumber = $data['payment_type'] === 'gcash' ? $data['gcash_number'] : null;
            $gcashName   = $data['payment_type'] === 'gcash' ? $data['gcash_name']   : null;
            $stmt->bind_param(
                'issssi',
                $userId,
                $data['payment_type'],
                $data['label'] ?? '',
                $gcashName,
                $gcashNumber,
                $defaultFlag
            );
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Payment method added.'];
        } catch (Throwable $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deletePaymentMethod(int $userId, int $paymentId): array
    {
        $stmt = $this->conn->prepare('DELETE FROM user_payment_methods WHERE userPaymentMethodId = ? AND userId = ?');
        $stmt->bind_param('ii', $paymentId, $userId);
        $success = $stmt->execute();
        $stmt->close();

        return $success
            ? ['success' => true, 'message' => 'Payment method deleted.']
            : ['success' => false, 'message' => 'Failed to delete payment method.'];
    }

    public function setDefaultPayment(int $userId, int $paymentId): array
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare('UPDATE user_payment_methods SET is_default = 0 WHERE userId = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->conn->prepare('UPDATE user_payment_methods SET is_default = 1 WHERE userPaymentMethodId = ? AND userId = ?');
            $stmt->bind_param('ii', $paymentId, $userId);
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Default payment updated.'];
        } catch (Throwable $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
