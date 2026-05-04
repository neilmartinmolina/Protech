/**
 * Admin / seller dashboard: DataTables, Chart.js, modal form mounts, photo upload, Toastify flash.
 * Expects window.__DASHBOARD__ set by dashboard.php (JSON).
 */
(() => {
    'use strict';

    const cfg = window.__DASHBOARD__ || {};

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
    });

    const tableDefs = cfg.dataTables || [];
    tableDefs.forEach((def) => {
        if (!def.selector || !document.querySelector(def.selector)) return;
        if (!window.jQuery || !window.jQuery.fn?.DataTable) return;
        // eslint-disable-next-line no-undef
        window.jQuery(def.selector).DataTable({
            paging: true,
            pagingType: 'simple_numbers',
            lengthChange: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
            searching: true,
            ordering: true,
            info: true,
            autoWidth: false,
            dom: "<'row align-items-center g-2 mb-3'<'col-sm-6'l><'col-sm-6'f>>" +
                "<'row'<'col-12'tr>>" +
                "<'row align-items-center g-2 mt-3'<'col-sm-6'i><'col-sm-6'p>>",
            order: def.order || [],
            columnDefs: def.disabledTargets?.length
                ? [{ orderable: false, targets: def.disabledTargets }]
                : [],
            language: {
                lengthMenu: 'Show _MENU_ rows',
                search: '',
                searchPlaceholder: def.placeholder || 'Search...',
                info: 'Showing _START_ to _END_ of _TOTAL_ rows',
                infoEmpty: 'Showing 0 rows',
                infoFiltered: '(filtered from _MAX_ total rows)',
                paginate: {
                    previous: 'Previous',
                    next: 'Next',
                },
            },
        });
    });

    function initProductPhotoUpload() {
        const zone       = document.getElementById('photoDropZone');
        const input      = document.getElementById('photoInput');
        const prompt     = document.getElementById('photoPrompt');
        const grid       = document.getElementById('photoPreviewGrid');
        const altPreview = document.getElementById('photoAltPreview');
        const altText    = document.getElementById('altPreviewText');
        const browse     = document.getElementById('photoBrowseTrigger');
        const nameInput  = document.getElementById('pf-name');
        const catSelect  = document.getElementById('pf-category');

        if (!zone || !input) return;

        let selectedFiles = [];
        const MAX_FILES   = 6;

        function buildAlt() {
            const name = (nameInput?.value || 'Product').trim();
            const cat  = catSelect?.value || 'Product';
            return name ? `${name} – ${cat}` : cat;
        }

        function updateAltPreview() {
            if (!altPreview || !altText) return;
            if (selectedFiles.length === 0) { altPreview.style.display = 'none'; return; }
            altText.textContent = buildAlt();
            altPreview.style.display = 'block';
        }

        nameInput?.addEventListener('input',  updateAltPreview);
        catSelect?.addEventListener('change', updateAltPreview);

        function renderPreviews() {
            if (selectedFiles.length === 0) {
                prompt.style.display = '';
                if (grid) { grid.style.display = 'none'; grid.innerHTML = ''; }
                if (altPreview) altPreview.style.display = 'none';
                return;
            }
            prompt.style.display = 'none';
            if (grid) {
                grid.style.display   = 'grid';
                grid.innerHTML = '';
            }

            selectedFiles.forEach((file, idx) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    if (!grid) return;
                    const card = document.createElement('div');
                    card.className = 'photo-preview-card';
                    card.innerHTML = `
                    <img src="${e.target.result}" alt="${escapeHtml(buildAlt())}" title="${escapeHtml(file.name)}">
                    <button type="button" class="photo-remove-btn" data-idx="${idx}" title="Remove">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    ${idx === 0 ? '<span class="photo-primary-badge">Cover</span>' : ''}
                `;
                    grid.appendChild(card);
                };
                reader.readAsDataURL(file);
            });

            updateAltPreview();
        }

        grid?.addEventListener('click', (e) => {
            const btn = e.target.closest('.photo-remove-btn');
            if (!btn) return;
            const idx = parseInt(btn.dataset.idx, 10);
            selectedFiles.splice(idx, 1);
            syncInputFiles();
            renderPreviews();
        });

        function syncInputFiles() {
            const dt = new DataTransfer();
            selectedFiles.forEach((f) => dt.items.add(f));
            input.files = dt.files;
        }

        function addFiles(newFiles) {
            const remaining = MAX_FILES - selectedFiles.length;
            const toAdd     = Array.from(newFiles).slice(0, remaining);
            selectedFiles.push(...toAdd);
            syncInputFiles();
            renderPreviews();
        }

        browse?.addEventListener('click', () => input.click());

        zone.addEventListener('click', (e) => {
            if (e.target === zone || e.target === prompt) input.click();
        });

        input.addEventListener('change', () => addFiles(input.files));

        zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave',  () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            addFiles(e.dataTransfer.files);
        });
    }

    function buildProductFormHtml() {
        const categories = cfg.categories || [];
        const brands     = cfg.brands || [];
        const categoryOptions = categories.map((c) =>
            `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
        const brandOptions = brands.map((b) =>
            `<option value="${escapeHtml(b)}"></option>`).join('');

        return `
            <form method="post" enctype="multipart/form-data" id="productForm" class="modal-form-grid two-col">
                <input type="hidden" name="action"     value="save_product">
                <input type="hidden" name="product_id" value="">
                <input type="hidden" name="icon_class" id="pf-icon-class" value="fa-solid fa-box-open">

                <div><label>Name</label><input name="name" type="text" id="pf-name" required></div>
                <div><label>Brand</label><input name="brand" type="text" list="brandList" required></div>

                <div><label>Category</label>
                    <select name="category" id="pf-category" required>
                        ${categoryOptions}
                    </select>
                </div>

                <div><label>Price</label><input name="price" type="number" step="0.01" min="0.01" required></div>
                <div><label>Stock</label><input name="stock" type="number" min="0" required></div>

                <div><label>Status</label>
                    <select name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div class="full"><label>Description</label><textarea name="description" required></textarea></div>

                <div class="full">
                    <label>Product Photos <span style="font-weight:400;color:var(--text-muted);font-size:.8rem;">(up to 6 · JPG / PNG / WebP · max 5 MB each)</span></label>
                    <div class="photo-upload-zone" id="photoDropZone">
                        <input type="file" name="product_images[]" id="photoInput"
                               accept="image/jpeg,image/png,image/webp"
                               multiple style="display:none;">
                        <div class="photo-upload-prompt" id="photoPrompt">
                            <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.6rem;color:var(--primary);"></i>
                            <p style="margin:.5rem 0 .2rem;font-weight:600;font-size:.9rem;">Drop photos here or <span style="color:var(--primary);cursor:pointer;" id="photoBrowseTrigger">browse</span></p>
                            <p style="margin:0;font-size:.75rem;color:var(--text-muted);">Alt text is auto-set from product category</p>
                        </div>
                        <div class="photo-preview-grid" id="photoPreviewGrid" style="display:none;"></div>
                    </div>
                    <p class="photo-alt-preview" id="photoAltPreview" style="margin:.45rem 0 0;font-size:.78rem;color:var(--text-muted);display:none;">
                        <i class="fa-solid fa-tag" style="margin-right:.3rem;"></i>
                        Alt text: "<span id="altPreviewText"></span>"
                    </p>
                </div>
            </form>
            <datalist id="brandList">${brandOptions}</datalist>
        `;
    }

    function mountModalForms() {
        const sellerModal = document.getElementById('sellerActionModal');
        if (sellerModal) {
            sellerModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" id="sellerActionForm">
                <input type="hidden" name="action" value="">
                <input type="hidden" name="application_id" value="">
            </form>
        `;
        }

        const rejectModal = document.getElementById('rejectSellerModal');
        if (rejectModal) {
            rejectModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" id="rejectSellerForm" class="modal-form-grid">
                <input type="hidden" name="action" value="reject_seller">
                <input type="hidden" name="application_id" value="">
                <div>
                    <label>Reason for rejection <span style="font-weight:400;color:var(--text-muted);">(optional — shown to applicant)</span></label>
                    <textarea name="rejection_reason" placeholder="e.g. Incomplete store information, please resubmit with more detail..."></textarea>
                </div>
            </form>
        `;
        }

        const productModal = document.getElementById('productModal');
        if (productModal) {
            productModal.querySelector('.app-modal__slot').innerHTML = buildProductFormHtml();
            initCategoryIconMap();
            initProductPhotoUpload();
        }

        const orderModal = document.getElementById('orderStatusModal');
        if (orderModal) {
            orderModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" id="orderStatusForm" class="modal-form-grid">
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" value="">
                <div><label>Status</label>
                    <select name="status">
                        <option value="placed">Placed</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </form>
        `;
        }

        const hideProductModal = document.getElementById('hideProductModal');
        if (hideProductModal) {
            hideProductModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" id="hideProductForm" class="modal-form-grid">
                <input type="hidden" name="action" value="hide_product">
                <input type="hidden" name="product_id" value="">
                <input type="hidden" name="hide" value="">
                <div>
                    <label>Reason <span style="font-weight:400;color:var(--text-muted);">(optional — recorded in activity log)</span></label>
                    <textarea name="reason" placeholder="Reason for hiding/unhiding this product..."></textarea>
                </div>
            </form>
        `;
        }

        const userModal = document.getElementById('userModal');
        if (userModal) {
            userModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" enctype="multipart/form-data" id="userForm" class="modal-form-grid two-col">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="user_id" value="">
                <div><label>First name</label><input type="text" name="first_name" required autocomplete="given-name"></div>
                <div><label>Last name</label><input type="text" name="last_name" required autocomplete="family-name"></div>
                <div><label>Username</label><input type="text" name="username" required autocomplete="username"></div>
                <div><label>Email</label><input type="email" name="email" required autocomplete="email"></div>
                <div><label>Role</label>
                    <select name="role" required>
                        <option value="customer">Customer</option>
                        <option value="seller">Seller</option>
                        <option value="admin">Admin</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>
                <div><label>Seller status</label>
                    <select name="seller_status">
                        <option value="not_applicable">Not applicable</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="full"><label>Store name</label><input type="text" name="store_name" placeholder="For seller accounts"></div>
                <div class="full"><label>Password</label><input type="password" name="password" autocomplete="new-password" placeholder="Required for new users; leave blank to keep when editing"></div>
                <div class="full"><label>Avatar</label><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif"></div>
            </form>
        `;
        }

        const deleteUserModal = document.getElementById('deleteUserModal');
        if (deleteUserModal) {
            deleteUserModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" id="deleteUserForm">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="">
            </form>
        `;
        }
    }

    function initCategoryIconMap() {
        const CATEGORY_ICON_MAP = {
            Laptops:     'fa-solid fa-laptop',
            Desktops:    'fa-solid fa-desktop',
            Peripherals: 'fa-solid fa-keyboard',
            Networking:  'fa-solid fa-network-wired',
        };

        const catSelect  = document.getElementById('pf-category');
        const iconInput  = document.getElementById('pf-icon-class');

        if (!catSelect || !iconInput) return;

        function syncIcon() {
            iconInput.value = CATEGORY_ICON_MAP[catSelect.value] ?? 'fa-solid fa-box-open';
        }

        catSelect.addEventListener('change', syncIcon);
        syncIcon();
    }

    mountModalForms();

    const adminOrdersLabels    = cfg.adminOrdersLabels || [];
    const adminOrdersData      = cfg.adminOrdersData || [];
    const adminStatusLabels    = cfg.adminStatusLabels || [];
    const adminStatusData      = cfg.adminStatusData || [];
    const sellerRevenueLabels  = cfg.sellerRevenueLabels || [];
    const sellerRevenueData    = cfg.sellerRevenueData || [];
    const sellerCategoryLabels = cfg.sellerCategoryLabels || [];
    const sellerCategoryData   = cfg.sellerCategoryData || [];

    function makeLineChart(id, labels, data, label, color) {
        const el = document.getElementById(id);
        if (!el || typeof Chart === 'undefined') return;
        // eslint-disable-next-line no-undef
        new Chart(el, {
            type: 'line',
            data: { labels,
                datasets: [{
                    label, data, borderColor: color,
                    backgroundColor: color.replace('1)', '.12)'),
                    fill: true, tension: 0.35,
                }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#b0b0b0' }, grid: { color: 'rgba(255,255,255,.05)' } },
                    y: { ticks: { color: '#b0b0b0' }, grid: { color: 'rgba(255,255,255,.05)' } },
                },
            },
        });
    }

    function makeDoughnutChart(id, labels, data) {
        const el = document.getElementById(id);
        if (!el || typeof Chart === 'undefined') return;
        // eslint-disable-next-line no-undef
        new Chart(el, {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: ['#ff7315', '#3b82f6', '#10b981', '#ef4444', '#f59e0b'] }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#b0b0b0' } } },
            },
        });
    }

    makeLineChart('adminOrdersChart',             adminOrdersLabels,    adminOrdersData,   'Orders',  'rgba(255,115,21,1)');
    makeDoughnutChart('adminStatusChart',         adminStatusLabels,    adminStatusData);
    makeLineChart('sellerRevenueChart',           sellerRevenueLabels,  sellerRevenueData, 'Revenue', 'rgba(16,185,129,1)');
    makeLineChart('sellerRevenueChartAnalytics',  sellerRevenueLabels,  sellerRevenueData, 'Revenue', 'rgba(16,185,129,1)');
    makeDoughnutChart('sellerCategoryChart',      sellerCategoryLabels, sellerCategoryData);
    makeDoughnutChart('sellerCategoryChartAnalytics', sellerCategoryLabels, sellerCategoryData);

    const flash = cfg.flash;
    if (flash && flash.message && typeof Toastify === 'function') {
        const type = flash.type || 'info';
        const bgByType = {
            success: 'linear-gradient(to right, #10b981, #059669)',
            warning: 'linear-gradient(to right, #f59e0b, #d97706)',
            danger:  'linear-gradient(to right, #ef4444, #dc2626)',
            info:    'linear-gradient(to right, #3b82f6, #2563eb)',
        };
        Toastify({
            text: flash.message,
            duration: 4500,
            gravity: 'top',
            position: 'right',
            close: true,
            stopOnFocus: true,
            style: {
                background: bgByType[type] || bgByType.info,
                borderRadius: '10px',
            },
        }).showToast();
    }
})();
