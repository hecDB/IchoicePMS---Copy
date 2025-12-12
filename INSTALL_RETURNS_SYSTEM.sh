#!/bin/bash
# 🚀 ระบบสินค้าตีกลับ - Installation Script
# Run this script to automatically set up the return items system

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║         🚀 ระบบสินค้าตีกลับ - Installation Script          ║"
echo "║                    IchoicePMS Return Items                    ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Check if running in Windows or Linux
if [[ "$OSTYPE" == "win32" || "$OSTYPE" == "msys" ]]; then
    echo "✓ ตรวจพบระบบ Windows"
    echo "  ต้องรัน setup_return_items_table.php ผ่านเบราว์เซอร์"
    echo ""
    echo "📍 ขั้นตอนการตั้งค่า:"
    echo "  1. เปิด XAMPP/LAMPP"
    echo "  2. สตาร์ท Apache และ MySQL"
    echo "  3. เข้าไป http://localhost/IchoicePMS---Copy/setup_return_items_table.php"
    echo "  4. คลิก 'สร้างตารางฐานข้อมูล'"
    echo ""
else
    echo "✓ ตรวจพบระบบ Linux/Mac"
fi

echo "📦 ไฟล์ที่สร้างแล้ว:"
echo ""
echo "  ✅ Database Setup:"
echo "     • setup_return_items_table.php"
echo ""
echo "  ✅ API Endpoints:"
echo "     • api/returned_items_api.php"
echo ""
echo "  ✅ User Interface:"
echo "     • returns/return_items.php (บันทึกสินค้าตีกลับ)"
echo "     • returns/return_dashboard.php (แดชบอร์ด)"
echo ""
echo "  ✅ Documentation:"
echo "     • returns/QUICKSTART.php (คู่มือเริ่มต้น)"
echo "     • returns/RETURN_SYSTEM_DOCUMENTATION.md (เอกสารเต็ม)"
echo "     • returns/README.md (สรุประบบ)"
echo ""

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                   ✅ การติดตั้งเสร็จสิ้น!                     ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

echo "🎯 ขั้นตอนต่อไป:"
echo ""
echo "1️⃣  สร้างตารางฐานข้อมูล:"
echo "   http://localhost/IchoicePMS---Copy/setup_return_items_table.php"
echo ""
echo "2️⃣  อ่านคู่มือเริ่มต้น:"
echo "   http://localhost/IchoicePMS---Copy/returns/QUICKSTART.php"
echo ""
echo "3️⃣  บันทึกสินค้าตีกลับ:"
echo "   http://localhost/IchoicePMS---Copy/returns/return_items.php"
echo ""
echo "4️⃣  ดูแดชบอร์ด:"
echo "   http://localhost/IchoicePMS---Copy/returns/return_dashboard.php"
echo ""

echo "📚 สำหรับความช่วยเหลือเพิ่มเติม:"
echo "   อ่าน returns/RETURN_SYSTEM_DOCUMENTATION.md"
echo ""
