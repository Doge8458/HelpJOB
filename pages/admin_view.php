<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista de Administrador - Todos los Empleos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #f4f4f9; font-family: 'Inter', sans-serif; }
        .status-active { background-color: #dcfce7; color: #166534; }
        .status-assigned { background-color: #dbeafe; color: #1e40af; }
        .status-expired_visible { background-color: #fef9c3; color: #854d0e; }
        .status-expired_hidden { background-color: #e5e7eb; color: #374151; }
    </style>
</head>
<body class="p-8">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-lg">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Vista de Administrador de Empleos</h1>
        <p class="text-gray-600 mb-4">Esta tabla muestra TODOS los trabajos directamente desde la base de datos, sin filtros. Es para depurar el estado de cada publicación.</p>
        
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 border-b">Post ID</th>
                        <th class="px-4 py-2 border-b">Employer ID</th>
                        <th class="px-4 py-2 border-b">Título</th>
                        <th class="px-4 py-2 border-b">Estado (post_status)</th>
                        <th class="px-4 py-2 border-b">Tipo (job_type)</th>
                        <th class="px-4 py-2 border-b">Categoría</th>
                        <th class="px-4 py-2 border-b">Fecha de Publicación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        require_once '../includes/db_connect.php';
                        
                        $sql = "SELECT post_id, employer_id, title, post_status, job_type, category, post_date FROM JobPostings ORDER BY post_id DESC";
                        $result = $conn->query($sql);

                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td class='border-b px-4 py-2 text-center font-medium'>" . htmlspecialchars($row['post_id']) . "</td>";
                                echo "<td class='border-b px-4 py-2 text-center'>" . htmlspecialchars($row['employer_id']) . "</td>";
                                echo "<td class='border-b px-4 py-2'>" . htmlspecialchars($row['title']) . "</td>";
                                
                                // Celda de estado con color
                                $status_class = 'status-' . strtolower(htmlspecialchars($row['post_status']));
                                echo "<td class='border-b px-4 py-2 text-center'><span class='px-2 py-1 rounded-full text-sm font-semibold " . $status_class . "'>" . htmlspecialchars($row['post_status']) . "</span></td>";
                                
                                echo "<td class='border-b px-4 py-2'>" . htmlspecialchars($row['job_type']) . "</td>";
                                echo "<td class='border-b px-4 py-2'>" . htmlspecialchars($row['category']) . "</td>";
                                echo "<td class='border-b px-4 py-2'>" . htmlspecialchars($row['post_date']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-center py-4'>No se encontraron trabajos en la tabla 'JobPostings'.</td></tr>";
                        }
                        $conn->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
