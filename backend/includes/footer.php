<?php
/**
 * Common Footer Include
 * Book Banko Admin Panel
 */
?>
        </main>
        
        <!-- Bottom Footer -->
        <footer class="text-center py-3 border-top bg-white text-secondary" style="font-size: 13px;">
            &copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong> Admin Portal &bull; Developed for Educational Excellence
        </footer>
    </div>
</div>

<!-- jQuery (Required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables & Responsive Extensions -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<!-- Supabase Client Config & Direct Uploader (Bypasses Serverless Request Size Limits) -->
<script>
window.SUPABASE_CONFIG = {
    url: '<?= defined('SUPABASE_URL') ? SUPABASE_URL : '' ?>',
    anonKey: '<?= defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : '' ?>'
};
</script>
<script src="<?= ASSETS_URL ?>js/supabase_uploader.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTables with responsive horizontal scrolling
    if ($('.data-table').length > 0) {
        $('.data-table').each(function() {
            var $table = $(this);
            // If table is inside .table-responsive, unwrap so DataTables scrollX controls only the table body
            // while search and pagination stay pinned to 100% of the visible card
            if ($table.parent().hasClass('table-responsive')) {
                $table.unwrap();
            }
            $table.DataTable({
                responsive: false,
                scrollX: true,
                scrollCollapse: true,
                autoWidth: false,
                pageLength: 10,
                language: {
                    search: "",
                    searchPlaceholder: "Search records...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    paginate: {
                        first: '<i class="bi bi-chevron-double-left"></i>',
                        previous: '<i class="bi bi-chevron-left"></i>',
                        next: '<i class="bi bi-chevron-right"></i>',
                        last: '<i class="bi bi-chevron-double-right"></i>'
                    }
                }
            });
        });

        // Auto-adjust columns once web fonts finish loading to prevent header misalignments
        if (document.fonts) {
            document.fonts.ready.then(function() {
                if ($.fn.dataTable) {
                    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
                }
            });
        }

        // Auto-adjust columns when any modal is shown or hidden
        $('.modal').on('shown.bs.modal hidden.bs.modal', function() {
            if ($.fn.dataTable) {
                $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
            }
        });
    }

    // Restore desktop sidebar collapsed preference if saved
    if (window.innerWidth >= 992) {
        if (localStorage.getItem('bb_sidebar_collapsed') === '1') {
            $('.app-wrapper').addClass('sidebar-collapsed');
        }
    }

    // Sidebar Toggle handling (Desktop collapse & Mobile drawer)
    $('#sidebarToggle').on('click', function(e) {
        e.preventDefault();
        if (window.innerWidth >= 992) {
            $('.app-wrapper').toggleClass('sidebar-collapsed');
            var isCollapsed = $('.app-wrapper').hasClass('sidebar-collapsed');
            localStorage.setItem('bb_sidebar_collapsed', isCollapsed ? '1' : '0');
        } else {
            $('#appSidebar').toggleClass('show');
            $('#sidebarBackdrop').toggleClass('show');
        }
        // Readjust DataTables columns after transition
        setTimeout(function() {
            if ($.fn.dataTable) {
                $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
            }
        }, 320);
    });

    // Close mobile drawer via close button or backdrop
    $('#sidebarCloseBtn, #sidebarBackdrop').on('click', function() {
        $('#appSidebar').removeClass('show');
        $('#sidebarBackdrop').removeClass('show');
    });

    // Auto-close mobile drawer when navigation item is clicked
    $('.sidebar-menu .nav-link-bb').on('click', function() {
        if (window.innerWidth < 992) {
            $('#appSidebar').removeClass('show');
            $('#sidebarBackdrop').removeClass('show');
        }
    });

    // Window resize handler: adjust DataTables and fix responsive states
    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth >= 992) {
                $('#appSidebar').removeClass('show');
                $('#sidebarBackdrop').removeClass('show');
                if (localStorage.getItem('bb_sidebar_collapsed') === '1') {
                    $('.app-wrapper').addClass('sidebar-collapsed');
                } else {
                    $('.app-wrapper').removeClass('sidebar-collapsed');
                }
            } else {
                $('.app-wrapper').removeClass('sidebar-collapsed');
            }
            if ($.fn.dataTable) {
                $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
            }
        }, 150);
    });

    // Auto dismiss flash alerts after 5 seconds
    setTimeout(function() {
        $('.custom-alert').fadeOut('slow');
    }, 5000);
});
</script>
</body>
</html>
