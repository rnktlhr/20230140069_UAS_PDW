<?php
// Include necessary files
require_once '../config.php'; // Pastikan path ke config.php benar

// Definisi variabel untuk template
$pageTitle = 'Praktikum Saya';
$activePage = 'my_courses'; // Menandai navigasi 'Praktikum Saya' sebagai aktif

// Include header mahasiswa
require_once 'templates/header_mahasiswa.php';

$enrolled_courses = [];
$message = '';

// Pastikan pengguna adalah mahasiswa dan sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    // Redireksi sudah di handle oleh header_mahasiswa.php, tapi bisa tambahkan pesan jika perlu
    $message = "Anda harus login sebagai mahasiswa untuk melihat halaman ini.";
} else {
    $current_user_id = $_SESSION['user_id'];

    // Mengambil daftar mata praktikum yang diikuti oleh mahasiswa yang sedang login
    $sql_enrolled_courses = "SELECT sc.id, c.id AS course_id, c.course_name, c.description, sc.enrollment_date
                             FROM student_courses sc
                             JOIN courses c ON sc.course_id = c.id
                             WHERE sc.user_id = ?
                             ORDER BY sc.enrollment_date DESC";

    $stmt_enrolled_courses = $conn->prepare($sql_enrolled_courses);
    $stmt_enrolled_courses->bind_param("i", $current_user_id);
    $stmt_enrolled_courses->execute();
    $result_enrolled_courses = $stmt_enrolled_courses->get_result();

    if ($result_enrolled_courses->num_rows > 0) {
        while ($row = $result_enrolled_courses->fetch_assoc()) {
            $enrolled_courses[] = $row;
        }
    } else {
        $message = "Anda belum terdaftar di praktikum manapun.";
    }
    $stmt_enrolled_courses->close();
}
$conn->close(); // Menutup koneksi database
?>

<div class="bg-gradient-to-r from-green-500 to-teal-400 text-white p-8 rounded-xl shadow-lg mb-8">
    <h1 class="text-3xl font-bold">Praktikum Saya</h1>
    <p class="mt-2 opacity-90">Lihat daftar praktikum yang sedang Anda ikuti.</p>
</div>

<?php if (!empty($message)): ?>
    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4" role="alert">
        <p><?php echo $message; ?></p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <?php if (empty($enrolled_courses)): ?>
        <?php if (empty($message)): // Hanya tampilkan jika tidak ada pesan lain ?>
            <p class="col-span-full text-center text-gray-600">Anda belum terdaftar di praktikum manapun. Silakan cari praktikum di halaman <a href="courses.php" class="text-blue-600 hover:underline">Cari Praktikum</a>.</p>
        <?php endif; ?>
    <?php else: ?>
        <?php foreach ($enrolled_courses as $course): ?>
            <div class="bg-white p-6 rounded-xl shadow-md flex flex-col">
                <h3 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                <p class="text-gray-600 text-sm flex-grow mb-4"><?php echo htmlspecialchars($course['description'] ?: 'Tidak ada deskripsi.'); ?></p>
                <div class="mt-auto">
                    <p class="text-xs text-gray-500 mb-2">Terdaftar sejak: <?php echo date('d M Y', strtotime($course['enrollment_date'])); ?></p>
                    <a href="course_detail.php?id=<?php echo $course['course_id']; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition-colors duration-300">
                        Lihat Detail & Tugas
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once 'templates/footer_mahasiswa.php';
?>