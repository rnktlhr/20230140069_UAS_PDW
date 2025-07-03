<?php
// Include necessary files
require_once '../config.php'; // Pastikan path ke config.php benar

// Definisi variabel untuk template (pageTitle akan diset setelah mengambil data course)
$pageTitle = 'Detail Praktikum';
$activePage = 'my_courses'; // Tetap aktif di 'Praktikum Saya' karena ini detailnya

// Include header mahasiswa
require_once 'templates/header_mahasiswa.php';

$course_id = $_GET['id'] ?? null;
$current_user_id = $_SESSION['user_id'] ?? null;
$course_details = null;
$modules_with_submissions = [];
$message = '';

// Direktori untuk menyimpan file materi dan laporan
$uploadDirMaterials = '../uploads/materials/';
$uploadDirSubmissions = '../uploads/submissions/';
if (!is_dir($uploadDirSubmissions)) {
    mkdir($uploadDirSubmissions, 0777, true);
}

// Pastikan course_id ada dan valid
if (empty($course_id) || !is_numeric($course_id)) {
    $message = "ID Praktikum tidak valid.";
} elseif (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    $message = "Anda harus login sebagai mahasiswa untuk melihat halaman ini.";
    // Redirect sudah ditangani header_mahasiswa.php, ini hanya untuk pesan
} else {
    // 1. Verifikasi apakah mahasiswa terdaftar di praktikum ini
    $stmt_check_enrollment = $conn->prepare("SELECT COUNT(*) FROM student_courses WHERE user_id = ? AND course_id = ?");
    $stmt_check_enrollment->bind_param("ii", $current_user_id, $course_id);
    $stmt_check_enrollment->execute();
    $stmt_check_enrollment->bind_result($is_enrolled);
    $stmt_check_enrollment->fetch();
    $stmt_check_enrollment->close();

    if ($is_enrolled === 0) {
        $message = "Anda tidak terdaftar di praktikum ini atau praktikum tidak ditemukan.";
    } else {
        // 2. Ambil detail praktikum
        $stmt_course = $conn->prepare("SELECT id, course_name, description FROM courses WHERE id = ?");
        $stmt_course->bind_param("i", $course_id);
        $stmt_course->execute();
        $result_course = $stmt_course->get_result();
        if ($result_course->num_rows > 0) {
            $course_details = $result_course->fetch_assoc();
            $pageTitle = htmlspecialchars($course_details['course_name']); // Set pageTitle
        }
        $stmt_course->close();

        // 3. Ambil semua modul untuk praktikum ini beserta status laporannya
        $sql_modules = "SELECT m.id AS module_id, m.module_name, m.description, m.material_file, m.due_date,
                               s.id AS submission_id, s.submission_file, s.submission_date, s.grade, s.feedback, s.status
                        FROM modules m
                        LEFT JOIN submissions s ON m.id = s.module_id AND s.user_id = ?
                        WHERE m.course_id = ?
                        ORDER BY m.due_date ASC";
        $stmt_modules = $conn->prepare($sql_modules);
        $stmt_modules->bind_param("ii", $current_user_id, $course_id);
        $stmt_modules->execute();
        $result_modules = $stmt_modules->get_result();
        if ($result_modules->num_rows > 0) {
            while ($row = $result_modules->fetch_assoc()) {
                $modules_with_submissions[] = $row;
            }
        }
        $stmt_modules->close();
    }
}

// Fungsi untuk mengelola upload file laporan
function handleSubmissionFileUpload($fileInputName, $uploadDir, $existingFileName = '') {
    global $message; // Menggunakan global message untuk menampilkan pesan error
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == UPLOAD_ERR_OK) {
        $fileName = basename($_FILES[$fileInputName]['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'doc', 'docx']; // Ekstensi file laporan yang diizinkan
        $maxFileSize = 10 * 1024 * 1024; // 10 MB

        if (!in_array($fileExt, $allowedExt)) {
            $message = "Error: Hanya file PDF, DOC, dan DOCX yang diizinkan untuk laporan.";
            return false;
        }
        if ($_FILES[$fileInputName]['size'] > $maxFileSize) {
            $message = "Error: Ukuran file laporan terlalu besar. Maksimal 10MB.";
            return false;
        }

        $newFileName = uniqid('submission_') . '.' . $fileExt; // Nama file unik
        $targetFilePath = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetFilePath)) {
            // Hapus file lama jika ini adalah re-submission
            if ($existingFileName && file_exists($uploadDir . $existingFileName)) {
                unlink($uploadDir . $existingFileName);
            }
            return $newFileName;
        } else {
            $message = "Error saat mengunggah file laporan.";
            return false;
        }
    }
    return null; // Tidak ada file diunggah
}

// Menangani Pengumpulan Laporan (File Upload)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_report'])) {
    $module_id_submission = $_POST['module_id'];

    if (!isset($_SESSION['user_id'])) {
        $message = "Anda harus login untuk mengumpulkan laporan.";
    } else {
        // Ambil data submission yang sudah ada (jika ada)
        $existing_submission_file = '';
        $stmt_existing = $conn->prepare("SELECT submission_file FROM submissions WHERE user_id = ? AND module_id = ?");
        $stmt_existing->bind_param("ii", $current_user_id, $module_id_submission);
        $stmt_existing->execute();
        $result_existing = $stmt_existing->get_result();
        if ($result_existing->num_rows > 0) {
            $existing_submission_file = $result_existing->fetch_assoc()['submission_file'];
        }
        $stmt_existing->close();

        $submission_file = handleSubmissionFileUpload('report_file', $uploadDirSubmissions, $existing_submission_file);

        if ($submission_file !== false && $submission_file !== null) { // Pastikan ada file yang diunggah atau diganti
            if ($existing_submission_file) {
                // Update laporan yang sudah ada
                $stmt_update_submission = $conn->prepare("UPDATE submissions SET submission_file = ?, submission_date = CURRENT_TIMESTAMP(), grade = NULL, feedback = NULL, status = 'submitted' WHERE user_id = ? AND module_id = ?");
                $stmt_update_submission->bind_param("sii", $submission_file, $current_user_id, $module_id_submission);
                if ($stmt_update_submission->execute()) {
                    $message = "Laporan berhasil diperbarui.";
                } else {
                    $message = "Error memperbarui laporan: " . $stmt_update_submission->error;
                }
                $stmt_update_submission->close();
            } else {
                // Tambah laporan baru
                $stmt_insert_submission = $conn->prepare("INSERT INTO submissions (module_id, user_id, submission_file) VALUES (?, ?, ?)");
                $stmt_insert_submission->bind_param("iis", $module_id_submission, $current_user_id, $submission_file);
                if ($stmt_insert_submission->execute()) {
                    $message = "Laporan berhasil dikumpulkan.";
                } else {
                    $message = "Error mengumpulkan laporan: " . $stmt_insert_submission->error;
                }
                $stmt_insert_submission->close();
            }
        } elseif ($submission_file === null && !$existing_submission_file) {
            $message = "Silakan pilih file laporan untuk diunggah.";
        }
    }
    // Redirect untuk menghindari resubmission form
    header("Location: course_detail.php?id=" . $course_id . "&msg=" . urlencode($message));
    exit();
}

// Menangani pesan dari redirect setelah submit laporan
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}
$conn->close(); // Menutup koneksi database setelah semua operasi selesai
?>

<div class="bg-gradient-to-r from-purple-500 to-pink-400 text-white p-8 rounded-xl shadow-lg mb-8">
    <?php if ($course_details): ?>
        <h1 class="text-3xl font-bold">Detail Praktikum: <?php echo htmlspecialchars($course_details['course_name']); ?></h1>
        <p class="mt-2 opacity-90"><?php echo htmlspecialchars($course_details['description'] ?: 'Tidak ada deskripsi.'); ?></p>
    <?php else: ?>
        <h1 class="text-3xl font-bold">Detail Praktikum</h1>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <div class="bg-<?php echo strpos($message, 'berhasil') !== false ? 'green' : 'red'; ?>-100 border-l-4 border-<?php echo strpos($message, 'berhasil') !== false ? 'green' : 'red'; ?>-500 text-<?php echo strpos($message, 'berhasil') !== false ? 'green' : 'red'; ?>-700 p-4 mb-4" role="alert">
        <p class="font-bold"><?php echo strpos($message, 'berhasil') !== false ? 'Sukses!' : 'Error!'; ?></p>
        <p><?php echo $message; ?></p>
    </div>
<?php endif; ?>

<?php if (!$course_details): ?>
    <p class="text-center text-gray-600">Praktikum tidak ditemukan atau Anda tidak memiliki akses.</p>
<?php else: ?>
    <div class="space-y-8">
        <?php if (empty($modules_with_submissions)): ?>
            <div class="bg-white p-6 rounded-xl shadow-md">
                <p class="text-gray-600">Belum ada modul yang ditambahkan untuk praktikum ini.</p>
            </div>
        <?php else: ?>
            <?php foreach ($modules_with_submissions as $module): ?>
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Modul: <?php echo htmlspecialchars($module['module_name']); ?></h3>
                    <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($module['description'] ?: 'Tidak ada deskripsi modul.'); ?></p>

                    <div class="mb-4">
                        <p class="font-semibold text-gray-700">Batas Waktu Pengumpulan:</p>
                        <p class="text-gray-600"><?php echo date('d M Y H:i', strtotime($module['due_date'])); ?></p>
                    </div>

                    <div class="mb-4">
                        <p class="font-semibold text-gray-700">Materi Modul:</p>
                        <?php if ($module['material_file']): ?>
                            <a href="<?php echo $uploadDirMaterials . htmlspecialchars($module['material_file']); ?>" target="_blank" class="text-blue-600 hover:underline">
                                Unduh Materi (<?php echo htmlspecialchars($module['material_file']); ?>)
                            </a>
                        <?php else: ?>
                            <p class="text-gray-600">Tidak ada materi tersedia.</p>
                        <?php endif; ?>
                    </div>

                    <h4 class="text-xl font-bold text-gray-800 mt-6 mb-3">Laporan & Penilaian</h4>
                    <?php if ($module['submission_id']): ?>
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-4">
                            <p class="text-gray-700">Status: <span class="font-semibold <?php echo ($module['status'] == 'graded') ? 'text-green-600' : 'text-yellow-600'; ?>"><?php echo ($module['status'] == 'graded') ? 'Sudah Dinilai' : 'Sudah Dikumpulkan (Menunggu Penilaian)'; ?></span></p>
                            <p class="text-gray-700">Dikumpulkan pada: <?php echo date('d M Y H:i', strtotime($module['submission_date'])); ?></p>
                            <p class="text-gray-700">File Laporan Anda: <a href="<?php echo $uploadDirSubmissions . htmlspecialchars($module['submission_file']); ?>" target="_blank" class="text-blue-600 hover:underline"><?php echo htmlspecialchars($module['submission_file']); ?></a></p>

                            <?php if ($module['status'] == 'graded'): ?>
                                <p class="text-gray-700">Nilai Anda: <span class="font-bold text-lg text-green-700"><?php echo htmlspecialchars($module['grade']); ?></span></p>
                                <p class="text-gray-700">Feedback: <span class="italic"><?php echo htmlspecialchars($module['feedback'] ?: 'Tidak ada feedback.'); ?></span></p>
                            <?php endif; ?>
                            <p class="text-sm text-gray-500 mt-2">Anda dapat mengunggah ulang laporan sebelum batas waktu.</p>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600 mb-3">Anda belum mengumpulkan laporan untuk modul ini.</p>
                    <?php endif; ?>

                    <?php
                    $is_past_due = (strtotime($module['due_date']) < time());
                    ?>

                    <?php if (!$is_past_due): ?>
                        <form action="course_detail.php?id=<?php echo $course_id; ?>" method="POST" enctype="multipart/form-data" class="space-y-3 mt-4">
                            <input type="hidden" name="module_id" value="<?php echo $module['module_id']; ?>">
                            <div>
                                <label for="report_file_<?php echo $module['module_id']; ?>" class="block text-gray-700 text-sm font-bold mb-2">Unggah/Perbarui Laporan (PDF/DOC/DOCX, Max 10MB):</label>
                                <input type="file" id="report_file_<?php echo $module['module_id']; ?>" name="report_file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                            </div>
                            <button type="submit" name="submit_report" class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-md transition-colors duration-300">
                                <?php echo $module['submission_id'] ? 'Perbarui Laporan' : 'Kumpulkan Laporan'; ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-red-600 mt-3">Batas waktu pengumpulan laporan untuk modul ini telah berakhir.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
// Include footer
require_once 'templates/footer_mahasiswa.php';
?>