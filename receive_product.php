<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/html5-qrcode"></script>
<style>
        .container { max-width: 800px; margin: 50px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { margin-bottom: 5px; }
        p { color: #555; margin-top: 0; margin-bottom: 20px; }
        .search-box { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-box input { flex: 1; padding: 12px 15px; font-size: 16px; border: 1px solid #ddd; border-radius: 8px; }
        .search-box button { padding: 0 15px; font-size: 20px; border: none; background: #1976d2; color: #fff; border-radius: 8px; cursor: pointer; }
        .search-box button:hover { background: #135ba1; }
        #reader-popup { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.6); justify-content: center; align-items: center; z-index:9999; }
        #reader-container { width:300px; height:300px; background:#fff; padding:10px; border-radius:12px; position:relative; }
        #close-btn { position:absolute; top:5px; right:5px; background:#f44336; color:#fff; border:none; border-radius:50%; width:30px; height:30px; cursor:pointer; font-weight:bold; }
        .result-item { display:flex; align-items:center; gap:10px; padding:10px; border-bottom:1px solid #eee; cursor:pointer; }
        .result-item img { width:40px; height:40px; object-fit:cover; border-radius:4px; }
        .result-info { display:flex; flex-direction:column; }
        .result-info div { font-size:13px; color:#666; }
        .result-info div.status { font-size:12px; color:#999; }
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="mainwrap">
    <div class="topbar">รับสินค้าเข้า</div>
    <div class="container">
        <h1>รับเข้าสินค้า</h1>
        <p>รับเข้าสินค้าและอัปเดตสต็อกคงเหลือ</p>
        <div class="search-box">
            <input type="text" id="search-input" placeholder="ค้นหาสินค้า (ชื่อ, SKU, บาร์โค้ด)">
            <button id="scan-btn"><span class="material-icons">qr_code_scanner</span></button>
        </div>
        <div id="results"></div>
    </div>
</div>

<!-- กล้อง popup -->
<div id="reader-popup">
    <div id="reader-container">
        <button id="close-btn">&times;</button>
        <div id="reader" style="width:100%; height:100%;"></div>
    </div>
</div>

<script>
const searchInput = document.getElementById("search-input");
const resultsDiv = document.getElementById("results");
const scanBtn = document.getElementById("scan-btn");
const readerPopup = document.getElementById("reader-popup");
const closeBtn = document.getElementById("close-btn");
const readerDiv = document.getElementById("reader");
let html5QrcodeScanner;
let lastDecoded = "";
let matchCount = 0;
let scanTimeout;

// เรียก API
async function searchProducts(query){
    try {
        const res = await fetch(`receive_product_API.php?q=${encodeURIComponent(query)}`);
        return await res.json();
    } catch(err) {
        console.error("Search error:", err);
        return [];
    }
}

// แสดงผลลัพธ์
function showResults(list){
    resultsDiv.innerHTML = "";

    if(!list || list.length === 0){
        resultsDiv.innerHTML = "<div style='padding:10px;color:#888'>ไม่พบสินค้า</div>";
        return;
    }

    list.forEach(item => {
        const po = item.po_list && item.po_list.length > 0 ? item.po_list[0] : null;
        const div = document.createElement('div');
        div.classList.add('result-item');
        div.innerHTML = `
            <img src="${item.image ?? 'no-image.png'}" style="width:40px;height:40px;object-fit:cover;border-radius:4px">
            <div class="result-info">
                <div><b>${item.name}</b></div>
                <div>SKU: ${item.sku} | หน่วย: ${item.unit}</div>
                <div class="status">PO: ${po ? po.po_number : '-'} | สถานะ: ${po ? po.status : '-'}</div>
            </div>
        `;
        div.addEventListener('click', () => openReceivePopup(item));
        resultsDiv.appendChild(div);
    });
}

// Popup รับสินค้าเข้า
function openReceivePopup(product){
    const po = product.po_list && product.po_list.length > 0 ? product.po_list[0] : null;

    Swal.fire({
        title: `รับสินค้าเข้า`,
        html: `
        <div style="text-align:left">
            <img src="${product.image ?? 'no-image.png'}" style="width:80px;height:80px;object-fit:cover;border-radius:6px;margin-bottom:10px">
            <p><b>สินค้า:</b> ${product.name} (${product.sku})</p>
            <p><b>PO:</b> ${po ? po.po_number : '-'}</p>
            <p><b>สถานะ:</b> ${po ? po.status : '-'}</p>

            <label>จำนวนรับเข้า:</label>
            <input type="number" id="qty-input" class="swal2-input" placeholder="จำนวน">

            <label>วันหมดอายุ:</label>
            <input type="date" id="exp-input" class="swal2-input">

            <label>แจ้งเตือนหมดอายุล่วงหน้า:</label>
            <select id="alert-input" class="swal2-input">
                <option value="7">7 วัน</option>
                <option value="14">14 วัน</option>
                <option value="30">30 วัน</option>
            </select>
        </div>
        `,
        showCancelButton: true,
        confirmButtonText: "บันทึกรับเข้า",
        cancelButtonText: "ยกเลิก",
        preConfirm: () => {
            return {
                qty: document.getElementById('qty-input').value,
                exp: document.getElementById('exp-input').value,
                alert: document.getElementById('alert-input').value,
                po_id: po ? po.po_id : null
            };
        }
    }).then(result => {
        if(result.isConfirmed){
            console.log("📦 รับเข้า:", { product, ...result.value });
            Swal.fire("สำเร็จ!", "บันทึกรับสินค้าเรียบร้อย", "success");
        }
    });
}


// ค้นหาแบบพิมพ์บางส่วน
let typingTimer;
searchInput.addEventListener("input",(e)=>{
    clearTimeout(typingTimer);
    const val = e.target.value.trim();
    if(!val){ resultsDiv.innerHTML=""; return; }
    
    typingTimer = setTimeout(async () => {
        const res = await searchProducts(val);
        if(res.status === "success"){
            showResults(res.data);
        } else {
            showResults([]);
        }
    }, 400);

});

// สแกนบาร์โค้ด
function onScanSuccess(decodedText){
    clearTimeout(scanTimeout);
    if(decodedText===lastDecoded) matchCount++;
    else { lastDecoded=decodedText; matchCount=1; }

    if(matchCount >= 2){
    (async ()=>{
        readerPopup.style.display="none";
        await html5QrcodeScanner.stop();

        const res = await searchProducts(decodedText);
        if(res.status === "success"){
            showResults(res.data);
        } else {
            showResults([]);
        }

        lastDecoded = "";
        matchCount = 0;
        Swal.fire("สแกนสำเร็จ", decodedText, "success");
    })();
}

}
function onScanError(err){}

scanBtn.addEventListener("click",()=>{
    readerPopup.style.display="flex";
    html5QrcodeScanner = new Html5Qrcode("reader");
    html5QrcodeScanner.start({facingMode:"environment"},{fps:5, qrbox:150},onScanSuccess,onScanError)
    .then(()=>{
        scanTimeout = setTimeout(()=>{
            Swal.fire("แจ้งเตือน","ไม่พบบาร์โค้ด โปรดลองใหม่","warning");
            html5QrcodeScanner.stop().finally(()=>{ readerPopup.style.display="none"; lastDecoded=""; matchCount=0; });
        },10000);
    }).catch(err=>{
        Swal.fire("ผิดพลาด","ไม่สามารถเข้าถึงกล้องได้","error");
        readerPopup.style.display="none";
    });
});

closeBtn.addEventListener("click",()=>{
    clearTimeout(scanTimeout);
    if(html5QrcodeScanner){
        html5QrcodeScanner.stop().finally(()=>{ readerPopup.style.display="none"; lastDecoded=""; matchCount=0; });
    } else { readerPopup.style.display="none"; lastDecoded=""; matchCount=0; }
});
</script>
</body>
</html>
