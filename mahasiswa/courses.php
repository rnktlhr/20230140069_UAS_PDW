<?php
// Include necessary files
require_once '../config.php'; // Pastikan path ke config.php benar

// Definisi variabel untuk template
$pageTitle = 'Cari Praktikum';
$activePage = 'courses'; // Menandai navigasi 'Cari Praktikum' sebagai aktif

// Include header
require_once 'templates/header_mahasiswa.php';

$message = '';
$available_courses = [];
$enrolled_course_ids = []; // Untuk menyimpan ID praktikum yang sudah diikuti mahasiswa

// Cek apakah mahasiswa sudah login
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] == 'mahasiswa';
$current_user_id = $_SESSION['user_id'] ?? null;

// Jika mahasiswa sudah login, ambil daftar praktikum yang sudah diikutinya
if ($is_logged_in) {
    $stmt_enrolled = $conn->prepare("SELECT course_id FROM student_courses WHERE user_id = ?");
    $stmt_enrolled->bind_param("i", $current_user_id);
    $stmt_enrolled->execute();
    $result_enrolled = $stmt_enrolled->get_result();
    while ($row = $result_enrolled->fetch_assoc()) {
        $enrolled_course_ids[] = $row['course_id'];
    }
    $stmt_enrolled->close();
}

// Menangani permintaan pendaftaran praktikum
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['enroll_course'])) {
    if (!$is_logged_in) {
        $message = "Anda harus login untuk mendaftar praktikum.";
    } else {
        $course_id_to_enroll = $_POST['course_id'];

        // Cek apakah sudah terdaftar
        if (in_array($course_id_to_enroll, $enrolled_course_ids)) {
            $message = "Anda sudah terdaftar di praktikum ini.";
        } else {
            $stmt_enroll = $conn->prepare("INSERT INTO student_courses (user_id, course_id) VALUES (?, ?)");
            $stmt_enroll->bind_param("ii", $current_user_id, $course_id_to_enroll);

            if ($stmt_enroll->execute()) {
                $message = "Berhasil mendaftar praktikum!";
                // Tambahkan ID kursus yang baru didaftarkan ke array agar tombol berubah status
                $enrolled_course_ids[] = $course_id_to_enroll;
            } else {
                $message = "Gagal mendaftar praktikum: " . $stmt_enroll->error;
            }
            $stmt_enroll->close();
        }
    }
}

// Mengambil semua mata praktikum yang tersedia
$sql_courses = "SELECT id, course_name, description FROM courses ORDER BY course_name ASC";
$result_courses = $conn->query($sql_courses);

if ($result_courses->num_rows > 0) {
    while ($row = $result_courses->fetch_assoc()) {
        $available_courses[] = $row;
    }
}
$conn->close();
?>

<div class="bg-gradient-to-r from-blue-500 to-cyan-400 text-white p-8 rounded-xl shadow-lg mb-8">
    <h1 class="text-3xl font-bold">Cari Praktikum Baru</h1>
    <p class="mt-2 opacity-90">Temukan praktikum yang sesuai dengan minat Anda dan segera daftarkan diri!</p>
</div>

<?php if (!empty($message)): ?>
    <div class="bg-<?php echo strpos($message, 'Berhasil') !== false ? 'green' : 'red'; ?>-100 border-l-4 border-<?php echo strpos($message, 'Berhasil') !== false ? 'green' : 'red'; ?>-500 text-<?php echo strpos($message, 'Berhasil') !== false ? 'green' : 'red'; ?>-700 p-4 mb-4" role="alert">
        <p class="font-bold"><?php echo strpos($message, 'Berhasil') !== false ? 'Sukses!' : 'Perhatian!'; ?></p>
        <p><?php echo $message; ?></p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <?php if (empty($available_courses)): ?>
        <p class="col-span-full text-center text-gray-600">Belum ada mata praktikum yang tersedia saat ini.</p>
    <?php else: ?>
        <?php foreach ($available_courses as $course): ?>
            <div class="bg-white p-6 rounded-xl shadow-md flex flex-col">
                <h3 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                <p class="text-gray-600 text-sm flex-grow mb-4"><?php echo htmlspecialchars($course['description'] ?: 'Tidak ada deskripsi.'); ?></p>
                <div class="mt-auto">
                    <?php if ($is_logged_in): ?>
                        <?php if (in_array($course['id'], $enrolled_course_ids)): ?>
                            <button class="bg-gray-400 text-white font-bold py-2 px-4 rounded-md cursor-not-allowed" disabled>
                                Sudah Terdaftar
                            </button>
                        <?php else: ?>
                            <form action="courses.php" method="POST">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" name="enroll_course" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition-colors duration-300">
                                    Daftar Sekarang
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="../login.php" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md transition-colors duration-300">
                            Login untuk Daftar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once 'templates/footer_mahasiswa.php';
?>