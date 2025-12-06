<!-- Stats Cards for Receive PO Items -->
<div class="row g-2 mb-4">
    <div class="col-xl-2 col-lg-4 col-md-6 mb-0">
        <div class="stats-card stats-primary" data-filter="all">
            <div class="stats-card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-title">รวมใบ PO</div>
                        <div class="stats-value"><?php echo count($all_pos); ?></div>
                        <div class="stats-subtitle">รอการรับหรือรับบางส่วน</div>
                    </div>
                    <div class="stats-icon" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #0c4a6e;">
                        <span class="material-icons">inventory_2</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-lg-4 col-md-6 mb-0">
        <div class="stats-card stats-success" data-filter="ready">
            <div class="stats-card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-title">พร้อมรับ</div>
                        <div class="stats-value"><?php echo $ready_to_receive; ?></div>
                        <div class="stats-subtitle">ยังไม่เริ่มรับสินค้า</div>
                    </div>
                    <div class="stats-icon" style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #166534;">
                        <span class="material-icons">check_circle</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-lg-4 col-md-6 mb-0">
        <div class="stats-card stats-warning" data-filter="partial">
            <div class="stats-card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-title">รับบางส่วน</div>
                        <div class="stats-value"><?php echo $partially_received; ?></div>
                        <div class="stats-subtitle">ยังคงต้องรับต่อ</div>
                    </div>
                    <div class="stats-icon" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #a16207;">
                        <span class="material-icons">schedule</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-lg-4 col-md-6 mb-0">
        <div class="stats-card stats-info" data-filter="completed">
            <div class="stats-card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-title">รับครบแล้ว</div>
                        <div class="stats-value"><?php echo $fully_received; ?></div>
                        <div class="stats-subtitle">ปิดรายการแล้ว</div>
                    </div>
                    <div class="stats-icon" style="background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 100%); color: #0c4a6e;">
                        <span class="material-icons">done_all</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-lg-4 col-md-6 mb-0">
        <div class="stats-card stats-danger" data-filter="cancelled" style="cursor: pointer;">
            <div class="stats-card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stats-title">ยกเลิก</div>
                        <div class="stats-value"><?php echo $cancelled_pos; ?></div>
                        <div class="stats-subtitle">
                            <a href="cancelled_items.php" class="text-danger text-decoration-none fw-bold small">
                                ดูรายละเอียด →
                            </a>
                        </div>
                    </div>
                    <div class="stats-icon" style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b;">
                        <span class="material-icons">cancel</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
