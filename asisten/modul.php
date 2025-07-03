<?php
// Include necessary files
require_once '../config.php'; // Pastikan path ke config.php benar

// Definisi variabel untuk template
$pageTitle = 'Manajemen Modul Praktikum';
$activePage = 'modul'; // Menandai navigasi 'Manajemen Modul' sebagai aktif

// Include header
require_once 'templates/header.php';

// Logika untuk operasi CRUD
$message = ''; // Untuk menampilkan pesan sukses atau error
$modules = []; // Array untuk menyimpan data modul
$courses = []; // Array untuk menyimpan daftar mata praktikum (untuk dropdown)

// Direktori untuk menyimpan file materi
$uploadDir = '../uploads/materials/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Buat direktori jika belum ada
}

// Mengambil daftar mata praktikum untuk dropdown
$sql_courses = "SELECT id, course_name FROM courses ORDER BY course_name ASC";
$result_courses = $conn->query($sql_courses);
if ($result_courses->num_rows > 0) {
    while ($row = $result_courses->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Fungsi untuk mengelola upload file
function handleFileUpload($fileInputName, $uploadDir, $existingFileName = '') {
    global $message;
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == UPLOAD_ERR_OK) {
        $fileName = basename($_FILES[$fileInputName]['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'docx', 'doc']; // Ekstensi file yang diizinkan
        $maxFileSize = 5 * 1024 * 1024; // 5 MB

        if (!in_array($fileExt, $allowedExt)) {
            $message = "Error: Hanya file PDF, DOCX, dan DOC yang diizinkan.";
            return false;
        }
        if ($_FILES[$fileInputName]['size'] > $maxFileSize) {
            $message = "Error: Ukuran file terlalu besar. Maksimal 5MB.";
            return false;
        }

        $newFileName = uniqid() . '.' . $fileExt; // Nama file unik
        $targetFilePath = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetFilePath)) {
            // Hapus file lama jika ada dan ini adalah update
            if ($existingFileName && file_exists($uploadDir . $existingFileName)) {
                unlink($uploadDir . $existingFileName);
            }
            return $newFileName;
        } else {
            $message = "Error saat mengunggah file.";
            return false;
        }
    } elseif ($existingFileName) {
        // Jika tidak ada file baru diunggah tapi ada file lama, pertahankan file lama
        return $existingFileName;
    }
    return null; // Tidak ada file baru dan tidak ada file lama
}


// Menangani submit form untuk Tambah/Edit/Hapus
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_module'])) {
        $course_id = $_POST['course_id'];
        $module_name = trim($_POST['module_name']);
        $description = trim($_POST['description']);
        $due_date = $_POST['due_date']; // Format YYYY-MM-DDTHH:MM

        if (empty($course_id) || empty($module_name) || empty($due_date)) {
            $message = "Semua field wajib diisi!";
        } else {
            $material_file = handleFileUpload('material_file', $uploadDir);

            if ($material_file !== false) { // Jika upload berhasil atau tidak ada file diunggah
                $stmt = $conn->prepare("INSERT INTO modules (course_id, module_name, description, material_file, due_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $course_id, $module_name, $description, $material_file, $due_date);

                if ($stmt->execute()) {
                    $message = "Modul berhasil ditambahkan.";
                } else {
                    $message = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['edit_module'])) {
        $module_id = $_POST['module_id'];
        $course_id = $_POST['course_id'];
        $module_name = trim($_POST['module_name']);
        $description = trim($_POST['description']);
        $due_date = $_POST['due_date'];

        if (empty($module_id) || empty($course_id) || empty($module_name) || empty($due_date)) {
            $message = "Semua field wajib diisi!";
        } else {
            // Ambil nama file materi lama untuk keperluan update/hapus jika diganti
            $old_material_file = '';
            $stmt_old_file = $conn->prepare("SELECT material_file FROM modules WHERE id = ?");
            $stmt_old_file->bind_param("i", $module_id);
            $stmt_old_file->execute();
            $result_old_file = $stmt_old_file->get_result();
            if ($result_old_file->num_rows > 0) {
                $old_material_file = $result_old_file->fetch_assoc()['material_file'];
            }
            $stmt_old_file->close();

            $material_file = handleFileUpload('edit_material_file', $uploadDir, $old_material_file);

            if ($material_file !== false) {
                $stmt = $conn->prepare("UPDATE modules SET course_id = ?, module_name = ?, description = ?, material_file = ?, due_date = ? WHERE id = ?");
                $stmt->bind_param("issssi", $course_id, $module_name, $description, $material_file, $due_date, $module_id);

                if ($stmt->execute()) {
                    $message = "Modul berhasil diperbarui.";
                } else {
                    $message = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['delete_module'])) {
        $module_id = $_POST['module_id'];

        if (!empty($module_id)) {
            // Ambil nama file materi untuk dihapus dari server
            $old_material_file = '';
            $stmt_old_file = $conn->prepare("SELECT material_file FROM modules WHERE id = ?");
            $stmt_old_file->bind_param("i", $module_id);
            $stmt_old_file->execute();
            $result_old_file = $stmt_old_file->get_result();
            if ($result_old_file->num_rows > 0) {
                $old_material_file = $result_old_file->fetch_assoc()['material_file'];
            }
            $stmt_old_file->close();

            $stmt = $conn->prepare("DELETE FROM modules WHERE id = ?");
            $stmt->bind_param("i", $module_id);

            if ($stmt->execute()) {
                // Hapus file dari direktori uploads jika ada
                if ($old_material_file && file_exists($uploadDir . $old_material_file)) {
                    unlink($uploadDir . $old_material_file);
                }
                $message = "Modul berhasil dihapus.";
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "ID modul tidak boleh kosong.";
        }
    }
}

// Mengambil semua modul dari database untuk ditampilkan
$sql_modules = "SELECT m.id, m.module_name, m.description, m.material_file, m.due_date, c.course_name
                FROM modules m
                JOIN courses c ON m.course_id = c.id
                ORDER BY c.course_name, m.due_date ASC";
$result_modules = $conn->query($sql_modules);

if ($result_modules->num_rows > 0) {
    while ($row = $result_modules->fetch_assoc()) {
        $modules[] = $row;
    }
}
$conn->close(); // Menutup koneksi database
?>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Tambah Modul Baru</h2>
    <?php if (!empty($message)): ?>
        <p class="text-green-500 mb-4"><?php echo $message; ?></p>
    <?php endif; ?>
    <form action="modul.php" method="POST" enctype="multipart/form-data" class="space-y-4">
        <div>
            <label for="course_id" class="block text-gray-700 text-sm font-bold mb-2">Pilih Mata Praktikum:</label>
            <select id="course_id" name="course_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                <option value="">-- Pilih Mata Praktikum --</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="module_name" class="block text-gray-700 text-sm font-bold mb-2">Nama Modul:</label>
            <input type="text" id="module_name" name="module_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>
        <div>
            <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi (Opsional):</label>
            <textarea id="description" name="description" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
        </div>
        <div>
            <label for="material_file" class="block text-gray-700 text-sm font-bold mb-2">File Materi (PDF/DOCX/DOC, Max 5MB):</label>
            <input type="file" id="material_file" name="material_file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
        </div>
        <div>
            <label for="due_date" class="block text-gray-700 text-sm font-bold mb-2">Batas Waktu Pengumpulan:</label>
            <input type="datetime-local" id="due_date" name="due_date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>
        <button type="submit" name="add_module" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Tambahkan Modul</button>
    </form>
</div>

<div class="bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Daftar Modul Praktikum</h2>
    <?php if (empty($modules)): ?>
        <p class="text-gray-600">Belum ada modul yang terdaftar.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mata Praktikum</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Modul</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batas Waktu</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Materi</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($modules as $module): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($module['course_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($module['module_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo date('d M Y H:i', strtotime($module['due_date'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($module['material_file']): ?>
                                    <a href="<?php echo $uploadDir . htmlspecialchars($module['material_file']); ?>" target="_blank" class="text-blue-600 hover:underline">Lihat File</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-4"
                                   onclick="openEditModal(
                                       <?php echo $module['id']; ?>,
                                       '<?php echo htmlspecialchars($module['course_id']); ?>',
                                       '<?php echo htmlspecialchars($module['module_name']); ?>',
                                       '<?php echo htmlspecialchars($module['description']); ?>',
                                       '<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($module['due_date']))); ?>',
                                       '<?php echo htmlspecialchars($module['material_file']); ?>'
                                   )">Edit</a>
                                <form action="modul.php" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus modul ini? File materi terkait juga akan dihapus.');">
                                    <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                    <button type="submit" name="delete_module" class="text-red-600 hover:text-red-900">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="editModuleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-1/3 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Edit Modul Praktikum</h3>
        <form action="modul.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" id="edit_module_id" name="module_id">
            <input type="hidden" id="edit_existing_material_file" name="existing_material_file">

            <div>
                <label for="edit_course_id" class="block text-gray-700 text-sm font-bold mb-2">Pilih Mata Praktikum:</label>
                <select id="edit_course_id" name="course_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">-- Pilih Mata Praktikum --</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="edit_module_name" class="block text-gray-700 text-sm font-bold mb-2">Nama Modul:</label>
                <input type="text" id="edit_module_name" name="module_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="edit_description" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi (Opsional):</label>
                <textarea id="edit_description" name="description" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
            </div>
            <div>
                <label for="edit_material_file" class="block text-gray-700 text-sm font-bold mb-2">Ganti File Materi (PDF/DOCX/DOC, Max 5MB):</label>
                <input type="file" id="edit_material_file" name="edit_material_file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p id="current_material_file_display" class="text-sm text-gray-500 mt-1"></p>
            </div>
            <div>
                <label for="edit_due_date" class="block text-gray-700 text-sm font-bold mb-2">Batas Waktu Pengumpulan:</label>
                <input type="datetime-local" id="edit_due_date" name="due_date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeEditModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Batal</button>
                <button type="submit" name="edit_module" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(id, course_id, module_name, description, due_date, material_file) {
        document.getElementById('edit_module_id').value = id;
        document.getElementById('edit_course_id').value = course_id; // Set selected option
        document.getElementById('edit_module_name').value = module_name;
        document.getElementById('edit_description').value = description;
        document.getElementById('edit_due_date').value = due_date; // Format datetime-local
        document.getElementById('edit_existing_material_file').value = material_file; // Untuk penanganan file lama
        
        const currentFileDisplay = document.getElementById('current_material_file_display');
        if (material_file) {
            currentFileDisplay.innerHTML = `File saat ini: <a href="../uploads/materials/${material_file}" target="_blank" class="text-blue-600 hover:underline">${material_file}</a>`;
        } else {
            currentFileDisplay.textContent = 'Tidak ada file materi saat ini.';
        }

        document.getElementById('editModuleModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModuleModal').classList.add('hidden');
    }
</script>

<?php
// Include footer
require_once 'templates/footer.php';
?>