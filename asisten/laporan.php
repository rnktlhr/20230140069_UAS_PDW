<?php
session_start();
ob_start();

require_once '../config.php'; // Koneksi DB

$pageTitle = 'Laporan Masuk';
$activePage = 'laporan';

// Direktori untuk menyimpan file laporan
$uploadDirSubmissions = '../uploads/submissions/';
if (!is_dir($uploadDirSubmissions)) {
    mkdir($uploadDirSubmissions, 0777, true);
}

$message = '';

// Tangani form submit penilaian
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_grade'])) {
    $submission_id = $_POST['submission_id'];
    $grade = $_POST['grade'];
    $feedback = trim($_POST['feedback']);

    if (!empty($submission_id) && is_numeric($grade)) {
        $stmt = $conn->prepare("UPDATE submissions SET grade = ?, feedback = ?, status = 'graded' WHERE id = ?");
        $stmt->bind_param("isi", $grade, $feedback, $submission_id);

        if ($stmt->execute()) {
            $message = "Nilai laporan berhasil disimpan.";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();

        header("Location: laporan.php?message=" . urlencode($message));
        exit();
    } else {
        $message = "Nilai atau ID laporan tidak valid.";
    }
}

// Tangkap pesan dari URL (jika ada)
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}

// Setelah proses logika selesai, baru tampilkan halaman
require_once 'templates/header.php';

// Ambil filter
$selected_module = $_GET['module_id'] ?? '';
$selected_student = $_GET['student_id'] ?? '';
$selected_status = $_GET['status'] ?? '';

// Dropdown filter
$modules_filter = [];
$students_filter = [];

$sql_modules_filter = "SELECT id, module_name FROM modules ORDER BY module_name ASC";
$result = $conn->query($sql_modules_filter);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $modules_filter[] = $row;
    }
}

$sql_students_filter = "SELECT id, nama FROM users WHERE role = 'mahasiswa' ORDER BY nama ASC";
$result = $conn->query($sql_students_filter);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $students_filter[] = $row;
    }
}

// Query laporan
$submissions = [];
$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($selected_module)) {
    $where_clauses[] = "s.module_id = ?";
    $params[] = $selected_module;
    $param_types .= 'i';
}
if (!empty($selected_student)) {
    $where_clauses[] = "s.user_id = ?";
    $params[] = $selected_student;
    $param_types .= 'i';
}
if (!empty($selected_status)) {
    $where_clauses[] = "s.status = ?";
    $params[] = $selected_status;
    $param_types .= 's';
}

$sql_submissions = "
    SELECT s.id, s.submission_file, s.submission_date, s.grade, s.feedback, s.status,
           u.nama AS student_name,
           m.module_name,
           c.course_name
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN modules m ON s.module_id = m.id
    JOIN courses c ON m.course_id = c.id
";

if (!empty($where_clauses)) {
    $sql_submissions .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql_submissions .= " ORDER BY s.submission_date DESC";

$stmt = $conn->prepare($sql_submissions);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}
$stmt->close();
$conn->close();
?>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Filter Laporan</h2>
    <form action="laporan.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="module_id">Modul:</label>
            <select name="module_id" class="w-full border rounded px-3 py-2">
                <option value="">Semua Modul</option>
                <?php foreach ($modules_filter as $module): ?>
                    <option value="<?= $module['id']; ?>" <?= ($selected_module == $module['id']) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($module['module_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="student_id">Mahasiswa:</label>
            <select name="student_id" class="w-full border rounded px-3 py-2">
                <option value="">Semua Mahasiswa</option>
                <?php foreach ($students_filter as $student): ?>
                    <option value="<?= $student['id']; ?>" <?= ($selected_student == $student['id']) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($student['nama']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status">Status:</label>
            <select name="status" class="w-full border rounded px-3 py-2">
                <option value="">Semua</option>
                <option value="submitted" <?= ($selected_status == 'submitted') ? 'selected' : ''; ?>>Belum Dinilai</option>
                <option value="graded" <?= ($selected_status == 'graded') ? 'selected' : ''; ?>>Sudah Dinilai</option>
            </select>
        </div>
        <div class="md:col-span-3 text-right">
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Terapkan Filter</button>
            <a href="laporan.php" class="bg-gray-300 px-4 py-2 rounded ml-2">Reset</a>
        </div>
    </form>
</div>

<div class="bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-4">Daftar Laporan Masuk</h2>
    <?php if ($message): ?>
        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-800 rounded">
            <?= $message; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($submissions)): ?>
        <p class="text-gray-600">Belum ada laporan ditemukan.</p>
    <?php else: ?>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left">Praktikum</th>
                    <th class="px-4 py-2 text-left">Modul</th>
                    <th class="px-4 py-2 text-left">Mahasiswa</th>
                    <th class="px-4 py-2 text-left">Tanggal</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left">Nilai</th>
                    <th class="px-4 py-2 text-left">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $s): ?>
                    <tr class="border-t">
                        <td class="px-4 py-2"><?= htmlspecialchars($s['course_name']); ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($s['module_name']); ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($s['student_name']); ?></td>
                        <td class="px-4 py-2"><?= date('d M Y H:i', strtotime($s['submission_date'])); ?></td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= ($s['status'] == 'graded') ? 'bg-green-200 text-green-800' : 'bg-yellow-200 text-yellow-800'; ?>">
                                <?= $s['status'] == 'graded' ? 'Sudah Dinilai' : 'Belum Dinilai'; ?>
                            </span>
                        </td>
                        <td class="px-4 py-2"><?= is_null($s['grade']) ? '-' : htmlspecialchars($s['grade']); ?></td>
                        <td class="px-4 py-2">
                            <a href="<?= $uploadDirSubmissions . $s['submission_file']; ?>" target="_blank" class="text-blue-500 hover:underline mr-2">Unduh</a>
                            <a href="#" class="text-indigo-600 hover:underline" onclick="openGradeModal(
                                <?= $s['id']; ?>,
                                '<?= htmlspecialchars($s['student_name']); ?>',
                                '<?= htmlspecialchars($s['module_name']); ?>',
                                '<?= htmlspecialchars($s['grade'] ?? ''); ?>',
                                '<?= htmlspecialchars($s['feedback'] ?? ''); ?>'
                            )">Nilai</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal -->
<div id="gradeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex justify-center items-start pt-20 z-50">
    <div class="bg-white p-6 rounded-lg shadow-lg w-96">
        <h3 class="text-lg font-bold mb-4">Penilaian Laporan</h3>
        <form action="laporan.php" method="POST">
            <input type="hidden" id="grade_submission_id" name="submission_id">
            <p><strong>Mahasiswa:</strong> <span id="grade_student_name"></span></p>
            <p><strong>Modul:</strong> <span id="grade_module_name"></span></p>
            <div class="mt-3">
                <label class="block mb-1">Nilai:</label>
                <input type="number" name="grade" id="grade_input" min="0" max="100" class="w-full border rounded px-3 py-2" required>
            </div>
            <div class="mt-3">
                <label class="block mb-1">Feedback:</label>
                <textarea name="feedback" id="feedback_input" rows="3" class="w-full border rounded px-3 py-2"></textarea>
            </div>
            <div class="mt-4 flex justify-end space-x-2">
                <button type="button" onclick="closeGradeModal()" class="bg-gray-400 text-white px-4 py-2 rounded">Batal</button>
                <button type="submit" name="submit_grade" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openGradeModal(id, student, module, grade, feedback) {
    document.getElementById('grade_submission_id').value = id;
    document.getElementById('grade_student_name').textContent = student;
    document.getElementById('grade_module_name').textContent = module;
    document.getElementById('grade_input').value = grade;
    document.getElementById('feedback_input').value = feedback;
    document.getElementById('gradeModal').classList.remove('hidden');
}
function closeGradeModal() {
    document.getElementById('gradeModal').classList.add('hidden');
}
</script>

<?php require_once 'templates/footer.php'; ?>
