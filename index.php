<!-- index.php -->
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>IchoicePMS</title>
    <style>
        body {
            background: #eef4fd;
            font-family: 'Sarabun', sans-serif;
        }
        .container {
            max-width: 700px;
            margin: 60px auto;
            text-align: center;
        }
        h1 {
            font-size: 54px;
            color: #193568;
            margin-bottom: 10px;
        }
        .subtitle {
            margin-bottom: 30px;
            font-size: 20px;
            color: #515e7a;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin: 35px 0;
        }
        .card {
            background: #fff;
            border-radius: 13px;
            box-shadow: 0 2px 10px #d5e1fa68;
            padding: 38px 10px;
            min-height: 170px;
            transition: transform .18s;
        }
        .card:hover { transform: translateY(-6px) scale(1.03); }
        .card img, .card svg {
            height: 45px;
            margin-bottom: 12px;
        }
        .card-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 7px;
        }
        .card-desc {
            color: #7c8da9;
            font-size: 15px;
        }
        .btn-login {
            background: #2267ee;
            color: #fff;
            padding: 14px 38px;
            border: none;
            border-radius: 7px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background .2s;
            margin-top: 40px;
        }
        .btn-login:hover { background: #193568; }
        .footer {
            margin-top: 18px;
            color: #b4b6bb;
            font-size: 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>IchoicePMS</h1>
    <div class="subtitle">
        จัดการสินค้า ติดตามสต็อก และควบคุมการเข้า-ออกสินค้าอย่างมีประสิทธิภาพ
    </div>
    <div class="grid">
        <div class="card">
            <div class="card-title">จัดการสินค้า</div>
            <div class="card-desc">เพิ่ม แก้ไข และติดตามสินค้าในคลัง</div>
        </div>
        <div class="card">
            <div class="card-title">รายงานสต็อก</div>
            <div class="card-desc">ตรวจสอบจำนวนสินค้าคงเหลือแบบเรียลไทม์</div>
        </div>
        <div class="card">
            <div class="card-title">ใบสั่งซื้อ</div>
            <div class="card-desc">สร้างและจัดการใบสั่งซื้อสินค้า</div>
        </div>
        <div class="card">
            <div class="card-title">จัดการผู้ใช้</div>
            <div class="card-desc">ควบคุมสิทธิการเข้าถึงตามแผนกงาน</div>
        </div>
    </div>

    <form action="auth/combined_login_register.php" method="get">
        <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
    </form>

    <div class="footer">
        ระบบรองรับแผนกต่างๆ: คลังสินค้า, ขาย, จัดซื้อ, ผู้จัดการ
    </div>
</div>
</body>
</html>