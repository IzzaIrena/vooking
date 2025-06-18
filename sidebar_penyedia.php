<div class="sidebar">
    <button onclick="goTo('penyedia_booking.php')" data-page="penyedia_booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</button>
    <button onclick="goTo('penyedia_home.php')" data-page="penyedia_home.php"><i class="fa-solid fa-eye"></i> Tampilkan Fasilitas</button>
    <button onclick="goTo('tambah_fasilitas.php')" data-page="tambah_fasilitas.php"><i class="fa-solid fa-plus"></i> Tambah Fasilitas</button>
    <button onclick="goTo('edit_fasilitas.php')" data-page="edit_fasilitas.php"><i class="fa-solid fa-pen-to-square"></i> Edit Fasilitas</button>
    <button onclick="goTo('verifikasi_pemesanan.php')" data-page="verifikasi_pemesanan.php"><i class="fa-solid fa-book"></i> Verifikasi Pemesanan</button>
    <button onclick="goTo('pemesanan_langsung.php')" data-page="pemesanan_langsung.php"><i class="fa-solid fa-book-open-reader"></i> Pemesanan Langsung</button>
    <button onclick="goTo('riwayat_booking_penyedia.php')" data-page="riwayat_booking_penyedia.php"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Booking</button>
</div>

<script>
function goTo(url) {
    window.location.href = url;
}

window.addEventListener("DOMContentLoaded", () => {
    const path = window.location.pathname.split("/").pop(); // Ambil nama file
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
</script>
