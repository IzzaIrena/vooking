<div class="sidebar">
    <div style="margin-bottom: 30px; font-weight: bold; color: #004080; font-size: 18px;">
        Menu Admin
    </div>
    <button onclick="goTo('dashboard_admin.php')" data-page="dashboard_admin.php"><i class="fa fa-chart-bar"></i> Dashboard</button>
    <button onclick="goTo('verifikasi_akun.php')" data-page="verifikasi_akun.php"><i class="fa fa-check-circle"></i> Verifikasi Akun</button>
    <button onclick="goTo('pembayaran_langganan.php')" data-page="pembayaran_langganan.php"><i class="fa fa-credit-card"></i> Pembayaran Langganan</button>
    <button onclick="goTo('laporan.php')" data-page="laporan.php"><i class="fa fa-file-alt"></i> Laporan</button>
    <button onclick="goTo('manajemen_akun.php')" data-page="manajemen_akun.php"><i class="fa fa-users"></i> Manajemen Akun</button>
    <button onclick="goTo('manajemen_fasilitas.php')" data-page="manajemen_fasilitas.php"><i class="fa fa-building"></i> Manajemen Fasilitas</button>
    <button onclick="konfirmasiLogout()" data-page="logout.php">
        <i class="fa fa-sign-out-alt"></i> Logout
    </button>
</div>

<script>
function goTo(url) {
    window.location.href = url;
}

window.addEventListener("DOMContentLoaded", () => {
    const path = window.location.pathname.split("/").pop(); // contoh: 'verifikasi_akun.php'
    const buttons = document.querySelectorAll(".sidebar button");

    buttons.forEach(btn => {
        const page = btn.getAttribute("data-page");
        if (page === path) {
            btn.classList.add("active");
        } else {
            btn.classList.remove("active");
        }
    });
});

function konfirmasiLogout() {
    const yakin = confirm("Apakah Anda yakin ingin logout?");
    if (yakin) {
        window.location.href = "logout.php";
    }
}

</script>


    <!-- <script>
//         $(document).ready(function() {
// var url = $(location).attr('href');
// console.log({ url });
//         });
function clickSideBar(id) {
    var url = window.location.href;
    var split_url = url.split('/');
    var last_url = split_url[split_url.length - 1];

    const elementBtn = document.getElementsByName("button");
    elementBtn.remove('active');
    const btn = document.getElementById(id);
        btn.classList.add("active");
    if (id === 'btn_dashboard') {
        location.href = "dashboard_admin.php";
    } else if (id === 'btn_verifikasi_akun') {
        location.href = "verifikasi_akn.php";
    }
}
        </script> -->