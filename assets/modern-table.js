/**
 * Modern Table Component JavaScript
 * IchoicePMS - Enhanced DataTable functionality
 * 
 * Features:
 * - Modern DataTable initialization
 * - Action button handlers  
 * - Responsive design
 * - Loading states
 * - Export functionality
 * - Batch operations
 */

class ModernTable {
    constructor(tableId, options = {}) {
        this.tableId = tableId;
        this.table = null;
        this.options = {
            pageLength: 25,
            language: 'th',
            responsive: true,
            searchPlaceholder: 'ค้นหา...',
            exportButtons: true,
            batchOperations: true,
            ...options
        };
        
        this.init();
    }

    init() {
        this.setupDataTable();
        this.setupEventHandlers();
        this.setupBatchOperations();
    }

    setupDataTable() {
        const languageConfig = this.getLanguageConfig();
        
        // Check if DataTable is already initialized and destroy it first
        const $table = $(`#${this.tableId}`);
        if ($.fn.DataTable.isDataTable($table)) {
            $table.DataTable().destroy();
        }
        
        this.table = $table.DataTable({
            pageLength: this.options.pageLength,
            language: languageConfig,
            responsive: this.options.responsive,
            dom: this.getDomLayout(),
            columnDefs: [
                { orderable: false, targets: 'no-sort' },
                { className: "text-center", targets: 'text-center' }
            ],
            order: this.options.defaultOrder || [[0, 'desc']],
            drawCallback: () => {
                this.setupActionButtons();
            },
            initComplete: () => {
                this.customizeSearchBox();
                this.addExportButtons();
            }
        });
    }

    getLanguageConfig() {
        if (this.options.language === 'th') {
            return {
                "decimal": "",
                "emptyTable": "ไม่มีข้อมูลในตาราง",
                "info": "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
                "infoEmpty": "แสดง 0 ถึง 0 จาก 0 รายการ",
                "infoFiltered": "(กรองจากทั้งหมด _MAX_ รายการ)",
                "infoPostFix": "",
                "thousands": ",",
                "lengthMenu": "แสดง _MENU_ รายการต่อหน้า",
                "loadingRecords": "กำลังโหลด...",
                "processing": "กำลังประมวลผล...",
                "search": "ค้นหา:",
                "zeroRecords": "ไม่พบรายการที่ตรงกัน",
                "paginate": {
                    "first": "หน้าแรก",
                    "last": "หน้าสุดท้าย", 
                    "next": "ถัดไป",
                    "previous": "ก่อนหน้า"
                },
                "aria": {
                    "sortAscending": ": เรียงลำดับจากน้อยไปมาก",
                    "sortDescending": ": เรียงลำดับจากมากไปน้อย"
                }
            };
        }
        return {}; // Default English
    }

    getDomLayout() {
        return '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>';
    }

    customizeSearchBox() {
        const searchInput = $(`#${this.tableId}_filter input`);
        searchInput.attr('placeholder', this.options.searchPlaceholder);
        searchInput.addClass('form-control form-control-sm');
        
        // Add search icon
        const searchContainer = $(`#${this.tableId}_filter`);
        searchContainer.addClass('position-relative');
        searchInput.css('padding-left', '2.5rem');
        searchContainer.prepend('<i class="material-icons position-absolute" style="left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 1.125rem;">search</i>');
    }

    addExportButtons() {
        if (!this.options.exportButtons) return;

        const buttonContainer = $(`#${this.tableId}_wrapper .row:first .col-md-6:first`);
        const exportHtml = `
            <div class="export-buttons mb-2">
                <button class="btn-modern btn-modern-success btn-sm me-2" id="export-excel">
                    <span class="material-icons" style="font-size: 1rem;">file_download</span>
                    Excel
                </button>
                <button class="btn-modern btn-modern-info btn-sm me-2" id="export-pdf">
                    <span class="material-icons" style="font-size: 1rem;">picture_as_pdf</span>
                    PDF  
                </button>
                <button class="btn-modern btn-modern-warning btn-sm" id="export-print">
                    <span class="material-icons" style="font-size: 1rem;">print</span>
                    พิมพ์
                </button>
            </div>
        `;
        buttonContainer.prepend(exportHtml);

        // Export handlers
        $('#export-excel').on('click', () => this.exportToExcel());
        $('#export-pdf').on('click', () => this.exportToPDF());
        $('#export-print').on('click', () => this.printTable());
    }

    setupBatchOperations() {
        if (!this.options.batchOperations) return;

        // Add select all checkbox to header
        const headerCheckbox = `<input type="checkbox" id="select-all-${this.tableId}" class="form-check-input">`;
        $(`#${this.tableId} thead tr`).prepend(`<th style="width: 40px;">${headerCheckbox}</th>`);

        // Add checkboxes to body rows
        $(`#${this.tableId} tbody tr`).each(function(index) {
            const checkbox = `<input type="checkbox" class="row-checkbox form-check-input" value="${index}">`;
            $(this).prepend(`<td>${checkbox}</td>`);
        });

        // Select all handler
        $(`#select-all-${this.tableId}`).on('change', function() {
            const isChecked = $(this).is(':checked');
            $(`.row-checkbox`).prop('checked', isChecked);
        });

        // Add batch action buttons
        const batchActionsHtml = `
            <div class="batch-actions mb-3" style="display: none;">
                <button class="btn-modern btn-modern-danger btn-sm" id="batch-delete">
                    <span class="material-icons" style="font-size: 1rem;">delete</span>
                    ลบที่เลือก (<span class="selected-count">0</span>)
                </button>
            </div>
        `;
        $(`#${this.tableId}_wrapper`).prepend(batchActionsHtml);

        // Update selected count
        $(document).on('change', '.row-checkbox', () => {
            const selectedCount = $('.row-checkbox:checked').length;
            $('.selected-count').text(selectedCount);
            $('.batch-actions').toggle(selectedCount > 0);
        });

        // Batch delete handler
        $('#batch-delete').on('click', () => this.handleBatchDelete());
    }

    setupEventHandlers() {
        // Refresh button
        $(document).on('click', '.refresh-table', () => {
            this.refreshTable();
        });

        // Row hover effects
        $(`#${this.tableId} tbody`).on('mouseenter', 'tr', function() {
            $(this).addClass('table-row-hover');
        }).on('mouseleave', 'tr', function() {
            $(this).removeClass('table-row-hover');
        });
    }

    setupActionButtons() {
        // View button handler
        $('.action-btn-view').off('click').on('click', (e) => {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            this.handleView(id);
        });

        // Edit button handler  
        $('.action-btn-edit').off('click').on('click', (e) => {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            this.handleEdit(id);
        });

        // Delete button handler
        $('.action-btn-delete').off('click').on('click', (e) => {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            this.handleDelete(id);
        });
    }

    handleView(id) {
        if (this.options.onView && typeof this.options.onView === 'function') {
            this.options.onView(id);
        } else {
            this.showMessage('ดูรายละเอียด ID: ' + id, 'info');
        }
    }

    handleEdit(id) {
        if (this.options.onEdit && typeof this.options.onEdit === 'function') {
            this.options.onEdit(id);
        } else {
            this.showMessage('แก้ไข ID: ' + id, 'info');
        }
    }

    handleDelete(id) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: 'คุณไม่สามารถย้อนกลับการดำเนินการนี้ได้!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                if (this.options.onDelete && typeof this.options.onDelete === 'function') {
                    this.options.onDelete(id);
                } else {
                    // Default delete handler
                    this.showMessage('ลบสำเร็จ!', 'success');
                    // Remove row from table
                    this.table.row(`[data-id="${id}"]`).remove().draw();
                }
            }
        });
    }

    handleBatchDelete() {
        const selectedIds = $('.row-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedIds.length === 0) {
            this.showMessage('กรุณาเลือกรายการที่ต้องการลบ', 'warning');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: `คุณต้องการลบ ${selectedIds.length} รายการหรือไม่?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                if (this.options.onBatchDelete && typeof this.options.onBatchDelete === 'function') {
                    this.options.onBatchDelete(selectedIds);
                } else {
                    this.showMessage(`ลบ ${selectedIds.length} รายการสำเร็จ!`, 'success');
                    // Remove selected rows
                    $('.row-checkbox:checked').closest('tr').remove();
                    this.table.draw();
                }
            }
        });
    }

    refreshTable() {
        const refreshBtn = $('.refresh-table');
        refreshBtn.addClass('loading').prop('disabled', true);
        
        // Simulate refresh
        setTimeout(() => {
            this.table.ajax.reload(null, false); // Keep current page
            refreshBtn.removeClass('loading').prop('disabled', false);
            this.showMessage('รีเฟรชข้อมูลสำเร็จ', 'success');
        }, 1500);
    }

    exportToExcel() {
        // Simple CSV export (can be enhanced with proper Excel export library)
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Get headers
        const headers = [];
        $(`#${this.tableId} thead th`).each(function() {
            const text = $(this).text().trim();
            if (text && text !== '') headers.push(text);
        });
        csvContent += headers.join(",") + "\n";

        // Get data
        this.table.rows().every(function() {
            const rowData = this.data();
            const row = [];
            for (let i = 0; i < rowData.length; i++) {
                // Clean HTML tags and get text content
                const cellData = $('<div>').html(rowData[i]).text().trim();
                row.push('"' + cellData.replace(/"/g, '""') + '"');
            }
            csvContent += row.join(",") + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `table_export_${new Date().getTime()}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        this.showMessage('ส่งออก Excel สำเร็จ', 'success');
    }

    exportToPDF() {
        // This would require a PDF library like jsPDF
        this.showMessage('กำลังพัฒนาฟีเจอร์ส่งออก PDF', 'info');
    }

    printTable() {
        const printWindow = window.open('', '', 'height=600,width=800');
        const tableHtml = $(`#${this.tableId}`).clone();
        
        // Remove action columns
        tableHtml.find('th:last-child, td:last-child').remove();
        
        printWindow.document.write(`
            <html>
                <head>
                    <title>พิมพ์รายงาน</title>
                    <style>
                        body { font-family: 'Sarabun', sans-serif; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; font-weight: bold; }
                        @media print {
                            body { margin: 0; }
                            table { font-size: 12px; }
                        }
                    </style>
                </head>
                <body>
                    <h2>รายงานข้อมูล</h2>
                    <p>วันที่พิมพ์: ${new Date().toLocaleDateString('th-TH')}</p>
                    ${tableHtml[0].outerHTML}
                </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();

        this.showMessage('เตรียมพิมพ์เรียบร้อย', 'success');
    }

    showMessage(message, type = 'info') {
        const icon = type === 'success' ? 'success' : 
                    type === 'error' ? 'error' : 
                    type === 'warning' ? 'warning' : 'info';

        Swal.fire({
            title: message,
            icon: icon,
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    // Public methods for external use
    addRow(data) {
        this.table.row.add(data).draw();
    }

    removeRow(selector) {
        this.table.row(selector).remove().draw();
    }

    updateRow(selector, data) {
        this.table.row(selector).data(data).draw();
    }

    search(term) {
        this.table.search(term).draw();
    }

    getSelectedIds() {
        return $('.row-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
    }

    clearSelection() {
        $('.row-checkbox, #select-all-' + this.tableId).prop('checked', false);
        $('.batch-actions').hide();
    }

    destroy() {
        if (this.table && $.fn.DataTable.isDataTable(`#${this.tableId}`)) {
            this.table.destroy();
            this.table = null;
        }
    }
}

// Initialize tables when document is ready - only auto-initialize tables with 'auto-modern-table' class
$(document).ready(function() {
    // Auto-initialize tables with class 'auto-modern-table' (not 'modern-table')
    // This prevents conflict with manual initialization
    $('.auto-modern-table').each(function() {
        const tableId = $(this).attr('id');
        if (tableId && !$.fn.DataTable.isDataTable(`#${tableId}`)) {
            new ModernTable(tableId);
        }
    });
});

// Export for use in other files
window.ModernTable = ModernTable;