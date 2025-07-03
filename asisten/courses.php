<?php
// Include necessary files
require_once '../config.php'; // Pastikan path ke config.php benar

// Definisi variabel untuk template
$pageTitle = 'Manajemen Mata Praktikum';
$activePage = 'courses'; // Menandai navigasi 'Manajemen Mata Praktikum' sebagai aktif

// Include header
require_once 'templates/header.php';

// Logika untuk operasi CRUD
$message = ''; // Untuk menampilkan pesan sukses atau error
$courses = []; // Array untuk menyimpan data mata praktikum

// Menangani submit form untuk Tambah/Edit/Hapus
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_course'])) {
        // Logika menambahkan mata praktikum baru
        $course_name = trim($_POST['course_name']);
        $description = trim($_POST['description']);

        if (!empty($course_name)) {
            // Menggunakan prepared statement untuk keamanan
            $stmt = $conn->prepare("INSERT INTO courses (course_name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $course_name, $description); // "ss" berarti dua string

            if ($stmt->execute()) {
                $message = "Mata praktikum berhasil ditambahkan.";
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "Nama mata praktikum tidak boleh kosong.";
        }
    } elseif (isset($_POST['edit_course'])) {
        // Logika mengedit mata praktikum yang sudah ada
        $course_id = $_POST['course_id'];
        $course_name = trim($_POST['course_name']);
        $description = trim($_POST['description']);

        if (!empty($course_name) && !empty($course_id)) {
            // Menggunakan prepared statement untuk keamanan
            $stmt = $conn->prepare("UPDATE courses SET course_name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $course_name, $description, $course_id); // "ssi" berarti dua string dan satu integer

            if ($stmt->execute()) {
                $message = "Mata praktikum berhasil diperbarui.";
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "Nama mata praktikum atau ID tidak boleh kosong.";
        }
    } elseif (isset($_POST['delete_course'])) {
        // Logika menghapus mata praktikum
        $course_id = $_POST['course_id'];

        if (!empty($course_id)) {
            // Menggunakan prepared statement untuk keamanan
            $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->bind_param("i", $course_id); // "i" berarti satu integer

            if ($stmt->execute()) {
                $message = "Mata praktikum berhasil dihapus.";
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "ID mata praktikum tidak boleh kosong.";
        }
    }
}

// Mengambil semua mata praktikum dari database untuk ditampilkan
$sql = "SELECT id, course_name, description FROM courses ORDER BY course_name ASC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}
$conn->close(); // Menutup koneksi database setelah semua operasi selesai
?>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Tambah Mata Praktikum Baru</h2>
    <?php if (!empty($message)): ?>
        <p class="text-green-500 mb-4"><?php echo $message; ?></p>
    <?php endif; ?>
    <form action="courses.php" method="POST" class="space-y-4">
        <div>
            <label for="course_name" class="block text-gray-700 text-sm font-bold mb-2">Nama Mata Praktikum:</label>
            <input type="text" id="course_name" name="course_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>
        <div>
            <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi (Opsional):</label>
            <textarea id="description" name="description" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
        </div>
        <button type="submit" name="add_course" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Tambahkan Mata Praktikum</button>
    </form>
</div>

<div class="bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Daftar Mata Praktikum</h2>
    <?php if (empty($courses)): ?>
        <p class="text-gray-600">Belum ada mata praktikum yang terdaftar.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Mata Praktikum</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($course['course_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($course['description']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-4" onclick="openEditModal(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_name']); ?>', '<?php echo htmlspecialchars($course['description']); ?>')">Edit</a>
                                <form action="courses.php" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus mata praktikum ini?');">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <button type="submit" name="delete_course" class="text-red-600 hover:text-red-900">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="editCourseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Edit Mata Praktikum</h3>
        <form action="courses.php" method="POST" class="space-y-4">
            <input type="hidden" id="edit_course_id" name="course_id">
            <div>
                <label for="edit_course_name" class="block text-gray-700 text-sm font-bold mb-2">Nama Mata Praktikum:</label>
                <input type="text" id="edit_course_name" name="course_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="edit_description" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi (Opsional):</label>
                <textarea id="edit_description" name="description" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
            </div>
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeEditModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Batal</button>
                <button type="submit" name="edit_course" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Fungsi untuk membuka modal edit dengan data yang sesuai
    function openEditModal(id, name, description) {
        document.getElementById('edit_course_id').value = id;
        document.getElementById('edit_course_name').value = name;
        document.getElementById('edit_description').value = description;
        document.getElementById('editCourseModal').classList.remove('hidden'); // Menampilkan modal
    }

    // Fungsi untuk menutup modal edit
    function closeEditModal() {
        document.getElementById('editCourseModal').classList.add('hidden'); // Menyembunyikan modal
    }
</script>

<?php
// Include footer
require_once 'templates/footer.php';
?>