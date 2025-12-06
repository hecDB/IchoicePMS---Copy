<!-- Filtered POs Display Section - Included when filter is applied -->
<div class="table-card">
    <div class="table-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="table-title mb-0">
                <span class="material-icons align-middle me-2">filter_list</span>
                <span id="filterTitle">รายการที่ต้องรับสินค้า</span>
            </h5>
            <div class="table-actions">
                <button class="btn btn-sm btn-outline-secondary" onclick="resetFilter()">
                    <span class="material-icons me-1" style="font-size: 0.9rem;">close</span>
                    ลบการกรอง
                </button>
            </div>
        </div>
    </div>
    <div class="table-body">
        <div class="row" id="filteredPoContainer">
            <!-- Filtered POs will be displayed here -->
        </div>
    </div>
</div>
