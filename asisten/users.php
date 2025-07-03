<?php
// Include necessary files
require_once '../config.php'; // Pastikan path ke config.php benar

// Definisi variabel untuk template
$pageTitle = 'Manajemen Akun Pengguna';
$activePage = 'users'; // Menandai navigasi 'Manajemen Pengguna' sebagai aktif

// Include header
require_once 'templates/header.php';

// Logika untuk operasi CRUD
$message = ''; // Untuk menampilkan pesan sukses atau error
$users = []; // Array untuk menyimpan data pengguna

// Menangani submit form untuk Tambah/Edit/Hapus
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Tambah Pengguna Baru
    if (isset($_POST['add_user'])) {
        $nama = trim($_POST['nama']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = trim($_POST['role']);

        if (empty($nama) || empty($email) || empty($password) || empty($role)) {
            $message = "Semua field wajib diisi!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Format email tidak valid!";
        } elseif (!in_array($role, ['mahasiswa', 'asisten'])) {
            $message = "Peran tidak valid!";
        } else {
            // Cek apakah email sudah terdaftar
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $message = "Email sudah terdaftar. Silakan gunakan email lain.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt_insert = $conn->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt_insert->bind_param("ssss", $nama, $email, $hashed_password, $role);

                if ($stmt_insert->execute()) {
                    $message = "Pengguna baru berhasil ditambahkan.";
                } else {
                    $message = "Error: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            }
            $stmt_check->close();
        }
    }
    // Edit Pengguna
    elseif (isset($_POST['edit_user'])) {
        $user_id = $_POST['user_id'];
        $nama = trim($_POST['nama']);
        $email = trim($_POST['email']);
        $role = trim($_POST['role']);
        $new_password = trim($_POST['new_password']); // Password baru (opsional)

        if (empty($user_id) || empty($nama) || empty($email) || empty($role)) {
            $message = "Nama, email, dan peran pengguna tidak boleh kosong.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Format email tidak valid!";
        } elseif (!in_array($role, ['mahasiswa', 'asisten'])) {
            $message = "Peran tidak valid!";
        } else {
            // Cek apakah email sudah terdaftar untuk user lain
            $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt_check_email->bind_param("si", $email, $user_id);
            $stmt_check_email->execute();
            $stmt_check_email->store_result();

            if ($stmt_check_email->num_rows > 0) {
                $message = "Email sudah terdaftar untuk pengguna lain.";
            } else {
                $sql_update = "UPDATE users SET nama = ?, email = ?, role = ?";
                $param_types = "sss";
                $params = [$nama, $email, $role];

                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $sql_update .= ", password = ?";
                    $param_types .= "s";
                    $params[] = $hashed_password;
                }
                $sql_update .= " WHERE id = ?";
                $param_types .= "i";
                $params[] = $user_id;

                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param($param_types, ...$params);

                if ($stmt_update->execute()) {
                    $message = "Akun pengguna berhasil diperbarui.";
                } else {
                    $message = "Error: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
            $stmt_check_email->close();
        }
    }
    // Hapus Pengguna
    elseif (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];

        // Pencegahan: Jangan biarkan admin menghapus akunnya sendiri
        if ($user_id == $_SESSION['user_id']) {
            $message = "Anda tidak bisa menghapus akun Anda sendiri!";
        } else {
            if (!empty($user_id)) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);

                if ($stmt->execute()) {
                    $message = "Akun pengguna berhasil dihapus.";
                } else {
                    $message = "Error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $message = "ID pengguna tidak boleh kosong.";
            }
        }
    }
}

// Mengambil semua pengguna dari database untuk ditampilkan
$sql = "SELECT id, nama, email, role FROM users ORDER BY nama ASC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
$conn->close(); // Menutup koneksi database
?>

<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Tambah Pengguna Baru</h2>
    <?php if (!empty($message)): ?>
        <p class="text-green-500 mb-4"><?php echo $message; ?></p>
    <?php endif; ?>
    <form action="users.php" method="POST" class="space-y-4">
        <div>
            <label for="nama" class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap:</label>
            <input type="text" id="nama" name="nama" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>
        <div>
            <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
            <input type="email" id="email" name="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>
        <div>
            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
            <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>
        <div>
            <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Peran:</label>
            <select id="role" name="role" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                <option value="mahasiswa">Mahasiswa</option>
                <option value="asisten">Asisten</option>
            </select>
        </div>
        <button type="submit" name="add_user" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Tambahkan Pengguna</button>
    </form>
</div>

<div class="bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Daftar Pengguna</h2>
    <?php if (empty($users)): ?>
        <p class="text-gray-600">Belum ada pengguna yang terdaftar.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Peran</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['nama']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-4"
                                   onclick="openEditModal(
                                       <?php echo $user['id']; ?>,
                                       '<?php echo htmlspecialchars($user['nama']); ?>',
                                       '<?php echo htmlspecialchars($user['email']); ?>',
                                       '<?php echo htmlspecialchars($user['role']); ?>'
                                   )">Edit</a>
                                <form action="users.php" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="text-red-600 hover:text-red-900">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="editUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Edit Pengguna</h3>
        <form action="users.php" method="POST" class="space-y-4">
            <input type="hidden" id="edit_user_id" name="user_id">
            <div>
                <label for="edit_nama" class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap:</label>
                <input type="text" id="edit_nama" name="nama" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="edit_email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                <input type="email" id="edit_email" name="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="edit_role" class="block text-gray-700 text-sm font-bold mb-2">Peran:</label>
                <select id="edit_role" name="role" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="mahasiswa">Mahasiswa</option>
                    <option value="asisten">Asisten</option>
                </select>
            </div>
            <div>
                <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">Password Baru (Kosongkan jika tidak diubah):</label>
                <input type="password" id="new_password" name="new_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeEditModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Batal</button>
                <button type="submit" name="edit_user" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(id, nama, email, role) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_role').value = role; // Set selected option
        document.getElementById('new_password').value = ''; // Kosongkan password baru setiap kali modal dibuka
        document.getElementById('editUserModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editUserModal').classList.add('hidden');
    }
</script>

<?php
// Include footer
require_once 'templates/footer.php';
?>