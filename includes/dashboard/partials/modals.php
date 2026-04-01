<?php
/** @var string $role */

require_once dirname(__DIR__, 3) . '/includes/modal.php';

if ($role === 'admin') {
    render_system_modal('sellerActionModal', 'Confirm Seller Action', 'Review this action before continuing.', 'Confirm');
    render_system_modal('rejectSellerModal', 'Reject Application', 'Provide a reason for rejection (optional).', 'Reject Application');
    render_system_modal('userModal', 'Save User', 'Create or update a user account.', 'Save User');
    render_system_modal('deleteUserModal', 'Delete User', 'This will permanently remove the user account.', 'Delete');
}

render_system_modal('orderStatusModal', 'Update Order Status', 'Update the status for this order.', 'Save Status');

if ($role === 'seller') {
    render_system_modal('productModal', 'Save Product', 'Create or update a product listing.', 'Save Product');
}
