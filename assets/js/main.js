document.addEventListener('DOMContentLoaded', () => {
    console.log('main.js loaded and executing.');

    // --- Variable Global para el estado del usuario ---
    let currentUser = null;

    // --- Funciones de Utilidad (Tu código original) ---
    function showMessage(message, isSuccess = false, containerId = 'message_box') {
        const messageBox = document.getElementById(containerId);
        if (messageBox) {
            messageBox.textContent = message;
            messageBox.className = 'p-3 my-4 rounded-lg text-center font-semibold text-white ';
            messageBox.classList.add(isSuccess ? 'bg-green-600' : 'bg-red-600', 'bg-opacity-80');
            messageBox.classList.remove('hidden');
            setTimeout(() => messageBox.classList.add('hidden'), 5000);
        }
    }

    function togglePasswordVisibility() {
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const passwordInput = document.getElementById(targetId);
                if (passwordInput) {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                }
            });
        });
    }

    // --- Lógica Principal de la Aplicación (Tu código original) ---
    async function main() {
        await checkUserSession();
        updateHeaderNav();
        routeToPageLogic();
        togglePasswordVisibility(); 
    }

    async function checkUserSession() {
        try {
            const response = await fetch('../api/auth.php?action=check_session');
            currentUser = await response.json();
            currentUser.logged_in = !!currentUser.success;
        } catch (error) {
            console.error('Could not check session:', error);
            currentUser = { logged_in: false };
        }
    }

    // --- FUNCIÓN ACTUALIZADA ---
    function updateHeaderNav() {
        if (!currentUser) return;
        const allDashboardLinks = document.querySelectorAll('#dashboardLink');
        const myApplicationsLink = document.querySelectorAll('#myApplicationsLink'); // Nuevo selector
        const allProfileSections = document.querySelectorAll('#profileSectionHeader');
        const allLoginButtons = document.querySelectorAll('#loginButtonHeader');
        const allRegisterButtons = document.querySelectorAll('#registerButtonHeader');
        
        if (currentUser.logged_in) {
            allProfileSections.forEach(el => el.classList.remove('hidden'));
            allLoginButtons.forEach(el => el.classList.add('hidden'));
            allRegisterButtons.forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('#profileNameHeader').forEach(el => el.textContent = currentUser.full_name || 'Mi Perfil');
            document.querySelectorAll('#profileImageHeader').forEach(el => {
                el.src = currentUser.profile_image_path && !currentUser.profile_image_path.includes('default') ? `../uploads/profile_images/${currentUser.profile_image_path}?t=${new Date().getTime()}` : `https://placehold.co/40x40/6a5acd/ffffff?text=${currentUser.full_name ? currentUser.full_name.charAt(0).toUpperCase() : 'U'}`;
            });

            // Lógica para mostrar/ocultar links específicos por rol
            if (currentUser.user_type === 'employer' || currentUser.user_type === 'company') {
                allDashboardLinks.forEach(el => el.classList.remove('hidden'));
                if(myApplicationsLink) myApplicationsLink.forEach(el => el.classList.add('hidden'));
            } else if (currentUser.user_type === 'applicant') {
                allDashboardLinks.forEach(el => el.classList.add('hidden'));
                if(myApplicationsLink) myApplicationsLink.forEach(el => el.classList.remove('hidden'));
            }

            document.querySelectorAll('#logoutButton').forEach(el => {
                const newEl = el.cloneNode(true);
                el.parentNode.replaceChild(newEl, el);
                newEl.addEventListener('click', handleLogout);
            });
        } else {
            allDashboardLinks.forEach(el => el.classList.add('hidden'));
            if(myApplicationsLink) myApplicationsLink.forEach(el => el.classList.add('hidden'));
            allProfileSections.forEach(el => el.classList.add('hidden'));
            allLoginButtons.forEach(el => el.classList.remove('hidden'));
            allRegisterButtons.forEach(el => el.classList.remove('hidden'));
        }

        const allMessagesLinks = document.querySelectorAll('#messagesLink');
        if (currentUser.logged_in) {
            allMessagesLinks.forEach(el => el.classList.remove('hidden'));
        } else {
            allMessagesLinks.forEach(el => el.classList.add('hidden'));
        }
    }
    
    async function handleLogout(e) {
        e.preventDefault();
        await fetch('../api/auth.php?action=logout');
        window.location.href = 'login.html';
    }

    // --- FUNCIÓN ACTUALIZADA ---
    function routeToPageLogic() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.html';
        const pageInitializers = {
            'index.html': initIndexPage,
            'job_listings.html': initJobListingsPage,
            'job_detail.html': initJobDetailPage,
            'login.html': initLoginPage,
            'register.html': initRegisterPage,
            'manage_jobs.html': initManageJobsPage,
            'post_job.html': initPostJobPage,
            'profile.html': initProfilePage,
            'my_applications.html': initMyApplicationsPage, // <-- NUEVA RUTA
            'view_applicants.html': initViewApplicantsPage, // <-- NUEVA RUTA
            'messages.html': initMessagesPage, // <-- NUEVA RUTA
        };
        const initializer = pageInitializers[currentPage];
        if (initializer) {
            console.log(`Initializing page: ${currentPage}`);
            initializer();
        }
    }

    // --- Lógica de Páginas Específicas (Funciones originales intactas) ---

    function initLoginPage() {
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault(); // <-- Esto es clave
                const formData = new FormData(loginForm);
                const data = Object.fromEntries(formData.entries());
                try {
                    const response = await fetch('../api/auth.php?action=login', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();
                    if (result.success) {
                        showMessage(result.message, true);
                        setTimeout(() => window.location.href = 'index.html', 1000);
                    } else {
                        showMessage(result.message, false);
                    }
                } catch (error) {
                    showMessage('Ocurrió un error en la conexión.', false);
                }
            });
        }
    }

    function initRegisterPage() {
        const form = document.getElementById('registerForm');
        if (!form) return;
        const userTypeSelect = document.getElementById('user_type');
        const applicantFields = document.getElementById('applicant_fields');
        const employerFields = document.getElementById('employer_company_fields');
        const applicantInputs = applicantFields.querySelectorAll('input, textarea');
        const employerInputs = employerFields.querySelectorAll('input, textarea');
        function toggleFields() {
            const type = userTypeSelect.value;
            applicantFields.classList.add('hidden');
            applicantInputs.forEach(i => i.required = false);
            employerFields.classList.add('hidden');
            employerInputs.forEach(i => i.required = false);
            if (type === 'applicant') {
                applicantFields.classList.remove('hidden');
            } else if (type === 'employer' || type === 'company') {
                employerFields.classList.remove('hidden');
                employerInputs.forEach(i => i.required = true);
            }
        }
        userTypeSelect.addEventListener('change', toggleFields);
        toggleFields();
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            if (data.password !== data.confirm_password) {
                return showMessage('Las contraseñas no coinciden.', false);
            }
            try {
                const response = await fetch('../api/auth.php?action=register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showMessage(result.message, true);
                    setTimeout(() => window.location.href = 'index.html', 1500);
                } else {
                    showMessage(result.message, false);
                }
            } catch (error) {
                showMessage('Ocurrió un error en la conexión.', false);
            }
        });
    }

    function initIndexPage() {
        // Lógica para la página de inicio
    }
    
    async function initJobListingsPage() {
        const container = document.getElementById('job_listings_container');
        const loadingSpinner = document.getElementById('loading_spinner');
        const noJobsMessage = document.getElementById('no_jobs_message');
        const paginationContainer = document.getElementById('pagination_container');

        const searchInput = document.getElementById('search_input');
        const categoryFilter = document.getElementById('filter_category');
        const jobTypeFilter = document.getElementById('filter_job_type');
        const sortByFilter = document.getElementById('sort_by_date');

        if (!container || !loadingSpinner || !paginationContainer) return;

        let debounceTimer;

        // Función principal para obtener y mostrar trabajos
        async function fetchAndDisplayJobs(page = 1) {
            loadingSpinner.classList.remove('hidden');
            noJobsMessage.classList.add('hidden');
            container.innerHTML = '';
            paginationContainer.innerHTML = '';

            const search = searchInput.value;
            const category = categoryFilter.value;
            const jobType = jobTypeFilter.value;
            const sortBy = sortByFilter.value;

            const queryParams = new URLSearchParams({
                action: 'list',
                search: search,
                category: category,
                job_type: jobType,
                sort_by_date: sortBy,
                page: page
            });

            try {
                const response = await fetch(`../api/jobs.php?${queryParams.toString()}`);
                const data = await response.json();
                
                loadingSpinner.classList.add('hidden');

                if (data.success && data.jobs && data.jobs.length > 0) {
                    data.jobs.forEach(job => container.appendChild(createJobCard(job)));
                    createPaginationControls(data.pagination.total_pages, data.pagination.current_page);
                } else {
                    noJobsMessage.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error fetching jobs:', error);
                loadingSpinner.classList.add('hidden');
                noJobsMessage.textContent = 'Error al cargar las ofertas de trabajo.';
                noJobsMessage.classList.remove('hidden');
            }
        }

        // Nueva función para crear los botones de paginación
        function createPaginationControls(totalPages, currentPage) {
            if (totalPages <= 1) return; // No mostrar paginación si solo hay una página

            for (let i = 1; i <= totalPages; i++) {
                const pageButton = document.createElement('button');
                pageButton.textContent = i;
                pageButton.className = 'px-4 py-2 rounded-lg transition-colors duration-200';

                if (i === currentPage) {
                    pageButton.className += ' bg-white text-primary-blue font-bold cursor-not-allowed';
                    pageButton.disabled = true;
                } else {
                    pageButton.className += ' bg-white bg-opacity-20 hover:bg-opacity-40 text-white';
                    pageButton.addEventListener('click', () => {
                        fetchAndDisplayJobs(i);
                        window.scrollTo(0, 0);
                    });
                }
                paginationContainer.appendChild(pageButton);
            }
        }

        function createJobCard(job) {
            const card = document.createElement('div');
            card.className = 'job-card bg-white bg-opacity-10 backdrop-filter backdrop-blur-lg rounded-xl shadow-lg p-6 flex flex-col transition-transform transform hover:scale-105 border border-white border-opacity-20';
            const employerImage = job.employer_profile_image && !job.employer_profile_image.includes('default') ? `../uploads/profile_images/${job.employer_profile_image}` : `https://placehold.co/48x48/8a2be2/ffffff?text=${(job.company_name_posting || 'C').charAt(0).toUpperCase()}`;
            const postDate = new Date(job.post_date).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
            card.innerHTML = `
                <div class="flex items-start mb-4">
                    <img src="${employerImage}" alt="Logo de la empresa" class="w-12 h-12 rounded-full mr-4 object-cover">
                    <div>
                        <h3 class="text-xl font-bold text-white">${job.title}</h3>
                        <p class="text-gray-300">${job.company_name_posting}</p>
                    </div>
                </div>
                <div class="space-y-3 text-gray-200 flex-grow">
                    <p><i class="fas fa-map-marker-alt w-5 mr-2 text-light-purple"></i>${job.job_location_text}</p>
                    <p><i class="fas fa-briefcase w-5 mr-2 text-light-purple"></i>${job.job_type}</p>
                    ${job.salary ? `<p><i class="fas fa-money-bill-wave w-5 mr-2 text-light-purple"></i>${job.salary}</p>` : ''}
                    <p><i class="fas fa-calendar-alt w-5 mr-2 text-light-purple"></i>Publicado: ${postDate}</p>
                </div>
                <div class="mt-6 text-center">
                    <a href="job_detail.html?post_id=${job.post_id}" class="w-full inline-block px-6 py-3 rounded-lg bg-gradient-to-r from-primary-blue to-secondary-purple hover:from-secondary-purple hover:to-primary-blue text-white font-semibold shadow-md transition-transform transform hover:scale-105">
                        Ver Detalles y Aplicar
                    </a>
                </div>`;
            return card;
        }

        const filterElements = [searchInput, categoryFilter, jobTypeFilter, sortByFilter];
        filterElements.forEach(el => {
            const eventType = el.tagName === 'INPUT' ? 'input' : 'change';
            el.addEventListener(eventType, () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => fetchAndDisplayJobs(1), 300);
            });
        });

        fetchAndDisplayJobs(1);
    }

    async function initManageJobsPage() {
        const container = document.getElementById('employer_jobs_list_container');
        const loadingSpinner = document.getElementById('loading_spinner_employer_jobs');
        const noJobsMessage = document.getElementById('no_employer_jobs_message');
        const modal = document.getElementById('deleteConfirmationModal');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        let jobToDeleteId = null;

        if (!container || !loadingSpinner || !noJobsMessage || !modal) return;

        const handleDeleteClick = (e) => {
            const deleteButton = e.target.closest('.delete-job-btn');
            if (deleteButton) {
                jobToDeleteId = deleteButton.dataset.postid;
                modal.classList.remove('hidden');
            }
        };

        container.addEventListener('click', handleDeleteClick);

        cancelDeleteBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
            jobToDeleteId = null;
        });

        confirmDeleteBtn.addEventListener('click', async () => {
            if (!jobToDeleteId) return;
            try {
                const response = await fetch('../api/jobs.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ post_id: jobToDeleteId })
                });
                const result = await response.json();
                showMessage(result.message, result.success, 'message_box');
                if (result.success) {
                    document.querySelector(`.delete-job-btn[data-postid='${jobToDeleteId}']`).closest('.employer-job-card').remove();
                }
            } catch (error) {
                showMessage('Error de conexión al eliminar.', false, 'message_box');
            } finally {
                modal.classList.add('hidden');
                jobToDeleteId = null;
            }
        });

        try {
            const response = await fetch('../api/jobs.php?action=list_by_employer');
            const data = await response.json();
            loadingSpinner.classList.add('hidden');
            if (data.success && data.jobs.length > 0) {
                data.jobs.forEach(job => container.appendChild(createEmployerJobCard(job)));
            } else {
                noJobsMessage.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error fetching employer jobs:', error);
            loadingSpinner.classList.add('hidden');
            noJobsMessage.textContent = 'Error al cargar tus publicaciones.';
            noJobsMessage.classList.remove('hidden');
        }
    }

    // --- FUNCIÓN ACTUALIZADA ---
    function createEmployerJobCard(job) {
        const card = document.createElement('div');
        card.className = 'employer-job-card bg-white bg-opacity-10 backdrop-filter backdrop-blur-lg rounded-xl p-6 flex flex-col md:flex-row justify-between items-center gap-4 border border-white border-opacity-20';
        const statusColors = { active: 'bg-green-500', assigned: 'bg-blue-500', expired_visible: 'bg-yellow-500', expired_hidden: 'bg-gray-500' };
        const statusText = { active: 'Activa', assigned: 'Asignada', expired_visible: 'Expirada', expired_hidden: 'Oculta' };
        card.innerHTML = `
            <div class="flex-grow">
                <h3 class="text-xl font-bold text-white">${job.title}</h3>
                <p class="text-gray-300">${job.company_name_posting} - ${job.job_location_text}</p>
                <div class="flex items-center gap-4 mt-2 text-sm">
                    <span class="px-2 py-1 rounded-full text-white ${statusColors[job.post_status] || 'bg-gray-400'}">${statusText[job.post_status] || 'Desconocido'}</span>
                    <span><i class="fas fa-users mr-1"></i> ${job.application_count} Aplicaciones</span>
                </div>
            </div>
            <div class="flex-shrink-0 flex gap-2">
                <!-- El enlace ahora apunta a la nueva página -->
                <a href="view_applicants.html?post_id=${job.post_id}" class="px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600 transition-colors">Ver Aplicantes</a>
                <a href="post_job.html?edit=${job.post_id}" class="edit-job-btn px-4 py-2 rounded-lg bg-yellow-500 hover:bg-yellow-600 transition-colors"><i class="fas fa-edit"></i></a>
                <button data-postid="${job.post_id}" class="delete-job-btn px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 transition-colors"><i class="fas fa-trash"></i></button>
            </div>`;
        return card;
    }

    // --- FUNCIÓN COMPLETADA ---
    async function initPostJobPage() {
        const form = document.getElementById('postJobForm');
        if (!form) return;

        const urlParams = new URLSearchParams(window.location.search);
        const editId = urlParams.get('edit');
        const isEditMode = !!editId;

        const latField = document.getElementById('latitude');
        const lonField = document.getElementById('longitude');
        const locationTextField = document.getElementById('job_location_text'); // Campo de texto visible
        const searchInput = document.getElementById('map_search');
        const searchBtn = document.getElementById('search_map_btn');
        const mapContainer = document.getElementById('map');

        if (mapContainer && typeof L !== 'undefined') {
            const initialCoords = [20.100, -98.766]; // Pachuca
            
            const map = L.map('map').setView(initialCoords, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            const marker = L.marker(initialCoords, { draggable: true }).addTo(map);

            function updateFields(latlng) {
                latField.value = latlng.lat.toFixed(6);
                lonField.value = latlng.lng.toFixed(6);
            }
            
            // --- CÓDIGO CORREGIDO (DRAGEND) ---
            marker.on('dragend', async (e) => {
                const latlng = e.target.getLatLng();
                updateFields(latlng); // Actualiza los campos ocultos

                // Realiza geocodificación inversa para obtener la dirección de texto
                try {
                    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}`);
                    const data = await response.json();
                    if (data && data.display_name) {
                        locationTextField.value = data.display_name; // Actualiza el campo de texto visible
                    }
                } catch (error) {
                    console.error('Error en la geocodificación inversa:', error);
                }
            });

            updateFields(marker.getLatLng());

            async function searchLocation() {
                const query = searchInput.value;
                if (!query) return;

                searchBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i>`;
                searchBtn.disabled = true;

                try {
                    const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`);
                    const data = await response.json();

                    // --- CÓDIGO CORREGIDO (SEARCH) ---
                    if (data && data.length > 0) {
                        const bestResult = data[0];
                        const coords = [bestResult.lat, bestResult.lon];
                        map.setView(coords, 16);
                        marker.setLatLng(coords);
                        updateFields({ lat: bestResult.lat, lng: bestResult.lon });
                        
                        // Actualiza también el campo de texto visible
                        locationTextField.value = bestResult.display_name;
                    } else {
                        showMessage('No se encontraron resultados para esa dirección.', false);
                    }

                } catch (error) {
                    showMessage('Error al buscar la dirección.', false);
                    console.error('Error de geocodificación:', error);
                } finally {
                    searchBtn.innerHTML = `<i class="fas fa-search"></i>`;
                    searchBtn.disabled = false;
                }
            }

            searchBtn.addEventListener('click', searchLocation);
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchLocation();
                }
            });

            if (isEditMode) {
                 try {
                    const response = await fetch(`../api/jobs.php?action=get_single&post_id=${editId}`);
                    const result = await response.json();
                    if (result.success && result.job.latitude && result.job.longitude) {
                        const savedCoords = [result.job.latitude, result.job.longitude];
                        map.setView(savedCoords, 15);
                        marker.setLatLng(savedCoords);
                        updateFields({ lat: result.job.latitude, lng: result.job.longitude });
                    }
                } catch (error) { console.error("Error fetching job for map edit:", error); }
            }
        }

        if (isEditMode) {
            document.getElementById('form-title').textContent = 'Editar Empleo';
            document.getElementById('form-subtitle').textContent = 'Actualiza los detalles de la vacante';
            document.getElementById('submit-button').textContent = 'Actualizar Empleo';
            try {
                const response = await fetch(`../api/jobs.php?action=get_single&post_id=${editId}`);
                const result = await response.json();
                if (result.success) {
                    const job = result.job;
                    document.getElementById('post_id').value = job.post_id;
                    document.getElementById('title').value = job.title;
                    document.getElementById('description').value = job.description;
                    document.getElementById('requirements').value = job.requirements;
                    document.getElementById('benefits').value = job.benefits;
                    document.getElementById('job_location_text').value = job.job_location_text;
                    document.getElementById('job_type').value = job.job_type;
                    document.getElementById('salary').value = job.salary;
                    document.getElementById('category').value = job.category;
                } else {
                    showMessage(result.message, false);
                }
            } catch (error) {
                showMessage('Error al cargar los datos del empleo.', false);
            }
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            const url = isEditMode ? `../api/jobs.php?action=update` : '../api/jobs.php?action=create';
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                showMessage(result.message, result.success);
                if (result.success) {
                    setTimeout(() => window.location.href = 'manage_jobs.html', 1500);
                }
            } catch (error) {
                showMessage('Error de conexión con el servidor.', false);
            }
        });
    }

    function initProfilePage() {
        const profileForm = document.getElementById('profileForm');
        if (!profileForm) return;

        let cropper;
        const cropperModal = document.getElementById('cropperModal');
        const imageToCrop = document.getElementById('imageToCrop');

        async function loadProfileData() {
            try {
                const response = await fetch(`../api/profile.php?action=get`);
                const result = await response.json();
                if (result.success) {
                    const userData = result.user_data;
                    document.getElementById('full_name').value = userData.full_name || '';
                    document.getElementById('email').value = userData.email || '';
                    document.getElementById('phone_number').value = userData.phone_number || '';
                    document.getElementById('location').value = userData.location || '';
                    document.getElementById('bio').value = userData.bio || '';

                    document.getElementById('profile_full_name').textContent = userData.full_name || 'Cargando...';
                    const profileImage = document.getElementById('current_profile_image');
                    profileImage.src = userData.profile_image_path && !userData.profile_image_path.includes('default')
                        ? `../uploads/profile_images/${userData.profile_image_path}?t=${new Date().getTime()}`
                        : `https://placehold.co/120x120/6a5acd/ffffff?text=${userData.full_name ? userData.full_name.charAt(0).toUpperCase() : 'U'}`;

                    if (userData.user_type === 'applicant') {
                        document.getElementById('applicant_profile_fields').classList.remove('hidden');
                        document.getElementById('work_experience').value = userData.work_experience || '';
                        document.getElementById('skills').value = userData.skills || '';
                        const cvFilename = document.getElementById('current_cv_filename');
                        const cvLink = document.getElementById('view_cv_link');
                        if (userData.cv_pdf_path) {
                            cvFilename.textContent = userData.cv_pdf_path.split('/').pop();
                            cvLink.href = `../uploads/cvs/${userData.cv_pdf_path}`;
                            cvLink.classList.remove('hidden');
                        }
                    } else if (userData.user_type === 'employer' || userData.user_type === 'company') {
                        document.getElementById('employer_profile_fields').classList.remove('hidden');
                        document.getElementById('company_name').value = userData.company_name || '';
                        document.getElementById('company_role').value = userData.company_role || '';
                    }
                }
            } catch (error) {
                showMessage('Error al cargar tu perfil.', false);
            }
        }
        
        document.getElementById('profile_image_upload').addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (event) => {
                imageToCrop.src = event.target.result;
                cropperModal.classList.remove('hidden');
                if (cropper) cropper.destroy();
                cropper = new Cropper(imageToCrop, { aspectRatio: 1, viewMode: 1, background: false });
            };
            reader.readAsDataURL(file);
        });

        document.getElementById('applyCrop').addEventListener('click', () => {
            if (!cropper) return;
            cropper.getCroppedCanvas({ width: 250, height: 250 }).toBlob(async (blob) => {
                const formData = new FormData();
                formData.append('profile_image', blob, 'profile.png');
                try {
                    const response = await fetch('../api/uploads.php?action=profile_image', { method: 'POST', body: formData });
                    const result = await response.json();
                    showMessage(result.message, result.success);
                    if (result.success) {
                        await checkUserSession();
                        updateHeaderNav();
                        await loadProfileData();
                    }
                } catch (error) {
                    showMessage('Error al subir la imagen.', false);
                } finally {
                    cropperModal.classList.add('hidden');
                    if (cropper) cropper.destroy();
                }
            }, 'image/png');
        });
        
        document.getElementById('cancelCrop').addEventListener('click', () => {
             cropperModal.classList.add('hidden');
             if(cropper) cropper.destroy();
        });

        document.getElementById('cv_upload').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if(!file) return;
            const formData = new FormData();
            formData.append('cv_pdf', file);
            try {
                const response = await fetch('../api/uploads.php?action=cv', { method: 'POST', body: formData });
                const result = await response.json();
                showMessage(result.message, result.success);
                if(result.success) await loadProfileData();
            } catch(error) {
                showMessage('Error al subir el CV.', false);
            }
        });

        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(profileForm);
            const data = Object.fromEntries(formData.entries());
            try {
                const response = await fetch('../api/profile.php?action=update', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                showMessage(result.message, result.success);
                if (result.success) {
                    await checkUserSession();
                    updateHeaderNav();
                }
            } catch (error) {
                showMessage('Error de conexión al actualizar el perfil.', false);
            }
        });

        if (currentUser && currentUser.logged_in) {
            loadProfileData();
        }
    }

    // --- FUNCIÓN ACTUALIZADA ---
    async function initJobDetailPage() {
        const mainContainer = document.getElementById('job_detail_page');
        const urlParams = new URLSearchParams(window.location.search);
        const postId = urlParams.get('post_id');

        if (!postId) {
            mainContainer.innerHTML = `<p class="text-center text-lg text-red-400">Error: No se ha especificado un ID de empleo.</p>`;
            return;
        }

        try {
            const response = await fetch(`../api/jobs.php?action=get_single&post_id=${postId}`);
            const result = await response.json();

            if (result.success && result.job) {
                displayJobDetails(result.job, mainContainer);

                const applyBtn = document.getElementById('applyBtn');
                const saveBtn = document.getElementById('saveBtn');

                if (applyBtn && currentUser && currentUser.user_type === 'applicant') {
                    applyBtn.addEventListener('click', () => {
                        const applyModal = createApplyModal(postId, result.job.title);
                        document.body.appendChild(applyModal);
                        applyModal.classList.remove('hidden');
                    });
                } else if(applyBtn) {
                     applyBtn.style.display = 'none';
                }
                
                if (saveBtn && currentUser && currentUser.user_type === 'applicant') {
                    saveBtn.addEventListener('click', () => handleSave(postId, saveBtn));
                } else if(saveBtn) {
                    saveBtn.style.display = 'none';
                }

            } else {
                mainContainer.innerHTML = `<p class="text-center text-lg text-red-400">${result.message || 'No se pudo cargar el empleo.'}</p>`;
            }
        } catch (error) {
            console.error('Error fetching job details:', error);
            mainContainer.innerHTML = `<p class="text-center text-lg text-red-400">Error de conexión al cargar los detalles.</p>`;
        }
    }
    
    function displayJobDetails(job, container) {
        try {
            const companyName = job.employer_company_name || 'Empresa';
            const employerImage = job.employer_profile_image && !job.employer_profile_image.includes('default') ? `../uploads/profile_images/${job.employer_profile_image}` : `https://placehold.co/80x80/8a2be2/ffffff?text=${companyName.charAt(0).toUpperCase()}`;
            const postDate = job.post_date ? new Date(job.post_date).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' }) : 'No disponible';
            const detailHTML = `
                <div class="bg-white bg-opacity-10 backdrop-filter backdrop-blur-lg rounded-xl shadow-2xl p-8 md:p-12 w-full max-w-4xl mx-auto border border-white border-opacity-20">
                    <div class="flex flex-col md:flex-row items-start gap-6 mb-8">
                        <img src="${employerImage}" alt="Logo de la empresa" class="w-20 h-20 rounded-full border-4 border-white object-cover flex-shrink-0">
                        <div>
                            <h1 class="text-3xl md:text-4xl font-bold">${job.title || 'Sin Título'}</h1>
                            <p class="text-xl text-gray-200">${companyName}</p>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-4 mb-8">
                        <button id="applyBtn" class="flex-1 py-3 px-6 rounded-lg bg-gradient-to-r from-primary-blue to-secondary-purple hover:from-secondary-purple hover:to-primary-blue text-white text-lg font-semibold shadow-md transition transform hover:scale-105"><i class="fas fa-paper-plane mr-2"></i>Aplicar Ahora</button>
                        <button id="saveBtn" class="flex-1 py-3 px-6 rounded-lg bg-white bg-opacity-20 hover:bg-opacity-30 text-white text-lg font-semibold shadow-md transition"><i class="fas fa-bookmark mr-2"></i>Guardar Empleo</button>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center mb-10">
                        <div><p class="text-sm text-gray-300">Ubicación</p><p class="font-bold text-lg">${job.job_location_text || 'No especificada'}</p></div>
                        <div><p class="text-sm text-gray-300">Jornada</p><p class="font-bold text-lg">${job.job_type || 'No especificada'}</p></div>
                        <div><p class="text-sm text-gray-300">Salario</p><p class="font-bold text-lg">${job.salary || 'No especificado'}</p></div>
                        <div><p class="text-sm text-gray-300">Publicado</p><p class="font-bold text-lg">${postDate}</p></div>
                    </div>
                    <div class="space-y-8">
                        <div><h2 class="text-2xl font-bold border-b-2 border-primary-blue pb-2 mb-4">Descripción del Puesto</h2><p class="text-gray-200 whitespace-pre-wrap">${job.description || 'Sin descripción.'}</p></div>
                        <div><h2 class="text-2xl font-bold border-b-2 border-primary-blue pb-2 mb-4">Requisitos</h2><p class="text-gray-200 whitespace-pre-wrap">${job.requirements || 'No especificados.'}</p></div>
                        <div><h2 class="text-2xl font-bold border-b-2 border-primary-blue pb-2 mb-4">Beneficios</h2><p class="text-gray-200 whitespace-pre-wrap">${job.benefits || 'No especificados.'}</p></div>
                    </div>
                    <div class="mt-10"><h2 class="text-2xl font-bold border-b-2 border-primary-blue pb-2 mb-4">Ubicación en el Mapa</h2><div id="detail_mapid" class="h-80 rounded-lg z-10"></div></div>
                </div>`;
            container.innerHTML = detailHTML;
            if (typeof L !== 'undefined' && job.latitude && job.longitude) {
                const map = L.map('detail_mapid').setView([job.latitude, job.longitude], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);
                L.marker([job.latitude, job.longitude]).addTo(map).bindPopup(`<b>${job.title}</b><br>${companyName}`).openPopup();
            } else {
                const mapContainer = document.getElementById('detail_mapid');
                if(mapContainer) mapContainer.innerHTML = '<p class="text-center text-gray-400">La ubicación exacta no fue proporcionada.</p>';
            }
        } catch (error) {
            console.error('Error dentro de displayJobDetails:', error);
            container.innerHTML = `<p class="text-center text-lg text-red-400">Ocurrió un error al mostrar los detalles del empleo.</p>`;
        }
    }


    async function handleApply(postId, message, button, modal) {
        try {
            const response = await fetch('../api/applications.php?action=apply', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ post_id: postId, message: message })
            });
            const result = await response.json();
            if (modal) {
                showMessage(result.message, result.success, 'apply-message-box');
            } else {
                alert(result.message);
            }
            if(result.success) {
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Ya has aplicado';
                }
                if (modal) {
                    setTimeout(() => modal.remove(), 2000);
                }
            }
        } catch (error) {
            if (modal) {
                showMessage('Error de conexión al aplicar.', false, 'apply-message-box');
            } else {
                alert('Error de conexión al aplicar.');
            }
        }
    }

    function createApplyModal(postId, jobTitle) {
        const modalId = 'applyModal';
        const existingModal = document.getElementById(modalId);
        if (existingModal) existingModal.remove();

        const modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-[1000]';
        modal.innerHTML = `
            <div class="bg-dark-bg p-8 rounded-lg shadow-xl w-11/12 max-w-lg text-white border border-white border-opacity-20">
                <h3 class="text-2xl font-bold mb-2">Aplicar para: ${jobTitle}</h3>
                <p class="text-gray-300 mb-6">Puedes incluir un mensaje para el empleador.</p>
                <form id="applyForm">
                    <textarea id="applyMessage" rows="5" class="w-full p-3 rounded-lg bg-white bg-opacity-20 border-white/30 focus:ring-2 focus:ring-primary-blue text-white" placeholder="Escribe tu mensaje aquí (opcional)..."></textarea>
                    <div id="apply-message-box" class="hidden"></div>
                    <div class="flex justify-end items-center gap-4 mt-6">
                        <button type="button" id="cancelApplyBtn" class="px-6 py-2 rounded-lg bg-gray-600 hover:bg-gray-700 transition font-semibold">Cancelar</button>
                        <button type="submit" class="px-6 py-2 rounded-lg bg-primary-blue hover:bg-secondary-purple transition font-bold">Enviar Aplicación</button>
                    </div>
                </form>
            </div>
        `;

        modal.querySelector('#cancelApplyBtn').addEventListener('click', () => {
            modal.remove();
        });

        modal.querySelector('#applyForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = modal.querySelector('#applyMessage').value;
            const applyBtn = document.getElementById('applyBtn');
            await handleApply(postId, message, applyBtn, modal);
        });

        return modal;
    }

    // --- MODIFICACIÓN EN handleSave: toggle guardar/quitar ---
    async function handleSave(postId, button) {
        try {
            const response = await fetch('../api/applications.php?action=save_job', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ post_id: postId })
            });
            const result = await response.json();
            alert(result.message);
            if(result.success) {
                if (result.action === 'saved') {
                    button.innerHTML = '<i class="fas fa-check mr-2"></i> Guardado';
                } else {
                    button.innerHTML = '<i class="fas fa-bookmark mr-2"></i> Guardar Empleo';
                }
            }
        } catch (error) {
            alert('Error de conexión al guardar.');
        }
    }

    function createApplicantCard(app) {
        const card = document.createElement('div');
        card.className = 'applicant-card bg-white bg-opacity-20 p-4 rounded-lg flex flex-col md:flex-row items-center justify-between gap-4';
        card.dataset.appid = app.application_id;
        card.dataset.applicant = JSON.stringify(app);

        const profileImg = app.profile_image_path && !app.profile_image_path.includes('default')
            ? `../uploads/profile_images/${app.profile_image_path}`
            : `https://placehold.co/48x48/6a5acd/ffffff?text=${app.full_name.charAt(0)}`;

        let statusHTML = '';
        const statusClasses = { pending: 'bg-yellow-500', in_review: 'bg-blue-500', interview: 'bg-purple-500', accepted: 'bg-green-500', rejected: 'bg-red-500' };
        const statusTexts = { pending: 'Pendiente', in_review: 'En Revisión', interview: 'Entrevista', accepted: 'Aceptado', rejected: 'Rechazado' };

        if (app.application_status === 'accepted' || app.application_status === 'rejected') {
             statusHTML = `<div class="status-text font-bold text-xl ${statusClasses[app.application_status].replace('bg-','text-').replace('-500','-400')}">${statusTexts[app.application_status].toUpperCase()}</div>`;
        } else {
            statusHTML = `
                <div class="flex flex-wrap gap-2 action-buttons">
                    <button class="view-profile-btn px-3 py-2 bg-blue-500 rounded-lg hover:bg-blue-600 transition text-sm"><i class="fas fa-eye"></i> Perfil</button>
                    <button class="start-chat-btn px-3 py-2 bg-purple-500 rounded-lg hover:bg-purple-600 transition text-sm"><i class="fas fa-comments"></i> Chatear</button>
                    <button class="accept-btn px-3 py-2 bg-green-500 rounded-lg hover:bg-green-600 transition text-sm"><i class="fas fa-check"></i> Aceptar</button>
                    <button class="reject-btn px-3 py-2 bg-red-500 rounded-lg hover:bg-red-600 transition text-sm"><i class="fas fa-times"></i> Rechazar</button>
                </div>`;
        }

        card.innerHTML = `
            <div class="flex items-center gap-4 flex-grow">
                <img src="${profileImg}" alt="Perfil" class="w-12 h-12 rounded-full object-cover">
                <div>
                    <p class="font-bold text-lg">${app.full_name}</p>
                    <div class="flex items-center gap-2">
                        <p class="text-sm text-gray-300">Aplicó: ${new Date(app.application_date).toLocaleDateString()}</p>
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full text-white ${statusClasses[app.application_status]}">${statusTexts[app.application_status]}</span>
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0">
                ${statusHTML}
            </div>
        `;
        return card;
    }

    async function initViewApplicantsPage() {
        console.log("Iniciando la página 'Ver Aplicantes'...");
        const urlParams = new URLSearchParams(window.location.search);
        const postId = urlParams.get('post_id');
        const listContainer = document.getElementById('applicants-list');
        const loadingSpinner = document.getElementById('loading_spinner');
        const noAppsMessage = document.getElementById('no-applicants-message');
        const modal = document.getElementById('applicantProfileModal');
        const modalContent = document.getElementById('modal-content');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const titleHeader = document.getElementById('job-title-header');

        if (!postId) {
            titleHeader.textContent = "Error";
            listContainer.innerHTML = `<p class="text-red-400 text-center">No se especificó una oferta de trabajo.</p>`;
            loadingSpinner.classList.add('hidden');
            return;
        }

        // 1. Cargar los datos de los aplicantes
        try {
            const response = await fetch(`../api/applications.php?action=list_job_applications&post_id=${postId}`);
            if (!response.ok) throw new Error(`Error del servidor: ${response.status}`);
            const result = await response.json();
            console.log("Respuesta de API (ver aplicantes):", result);
            loadingSpinner.classList.add('hidden');

            if (result.success) {
                titleHeader.textContent = `Aplicantes para: ${result.job_title}`;
                if (result.applications.length > 0) {
                    listContainer.innerHTML = '';
                    result.applications.forEach(app => listContainer.appendChild(createApplicantCard(app)));
                } else {
                    noAppsMessage.classList.remove('hidden');
                }
            } else {
                titleHeader.textContent = "Error";
                listContainer.innerHTML = `<p class="text-red-400 text-center">${result.message}</p>`;
            }
        } catch (error) {
            console.error("FALLO al cargar los aplicantes:", error);
            loadingSpinner.classList.add('hidden');
            titleHeader.textContent = "Error de Conexión";
            listContainer.innerHTML = `<p class="text-red-400 text-center">Error al cargar los aplicantes. Revisa la consola (F12).</p>`;
        }

        // 2. Delegación de eventos para TODOS los botones de la tarjeta
        if (listContainer) {
            listContainer.addEventListener('click', async (e) => {
                const card = e.target.closest('.applicant-card');
                if (!card) return;

                const appId = card.dataset.appid;
                const applicantData = JSON.parse(card.dataset.applicant);

                if (e.target.closest('.view-profile-btn')) {
                    modalContent.innerHTML = createProfileModalContent(applicantData);
                    modal.classList.remove('hidden');
                } 
                else if (e.target.closest('.start-chat-btn')) {
                    openChatWidgetWithUser(applicantData.user_id, applicantData.full_name, applicantData.profile_image_path);
                } 
                else if (e.target.closest('.accept-btn')) {
                    await updateApplicationStatus(appId, 'accepted', card);
                } 
                else if (e.target.closest('.reject-btn')) {
                    await updateApplicationStatus(appId, 'rejected', card);
                }
            });
        }

        // 3. Cerrar el modal de perfil
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));
        }
    }

    // --- FUNCIONES DE SOPORTE PARA initViewApplicantsPage ---
    function createProfileModalContent(applicant) {
        const cvLink = applicant.cv_pdf_path 
            ? `<a href="../uploads/cvs/${applicant.cv_pdf_path}" target="_blank" class="text-blue-400 hover:underline">Ver CV (PDF)</a>` 
            : 'No ha subido CV.';
        
        return `
            <h2 class="text-2xl font-bold mb-4">${applicant.full_name}</h2>
            <p class="mb-2"><strong>Email:</strong> ${applicant.email}</p>
            <p class="mb-2"><strong>Teléfono:</strong> ${applicant.phone_number || 'No especificado'}</p>
            <p class="mb-4"><strong>Biografía:</strong><br><span class="text-gray-300">${applicant.bio || 'No especificada.'}</span></p>
            <p class="mb-4"><strong>Habilidades:</strong><br><span class="text-gray-300">${applicant.skills || 'No especificadas.'}</span></p>
            <p class="mb-4"><strong>Experiencia:</strong><br><span class="text-gray-300">${applicant.work_experience || 'No especificada.'}</span></p>
            <p><strong>CV:</strong> ${cvLink}</p>
        `;
    }

    async function updateApplicationStatus(applicationId, status, cardElement) {
        try {
            const response = await fetch('../api/applications.php?action=update_application_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ application_id: applicationId, status: status })
            });
            const result = await response.json();
            
            showMessage(result.message, result.success, 'message_box');

            if (result.success) {
                const actionButtons = cardElement.querySelector('.action-buttons');
                if (actionButtons) actionButtons.remove();
                
                const statusClass = status === 'accepted' ? 'text-green-400' : 'text-red-400';
                const statusText = status === 'accepted' ? 'ACEPTADO' : 'RECHAZADO';
                
                let statusDiv = cardElement.querySelector('.status-text');
                if (!statusDiv) {
                    statusDiv = document.createElement('div');
                    cardElement.querySelector('.flex-shrink-0').appendChild(statusDiv);
                }
                statusDiv.className = `status-text font-bold text-xl ${statusClass}`;
                statusDiv.textContent = statusText;
            }
        } catch (error) {
            console.error("Error al actualizar estado:", error);
            showMessage('Error de conexión al actualizar el estado.', false, 'message_box');
        }
    }

    // --- FUNCIONES DE CHAT
    function initChatWidget() {
        const chatWidget = document.getElementById('chat_widget');
        if (!chatWidget) return;

        chatWidget.classList.remove('hidden');
        startChatPolling();
    }

    function startChatPolling() {
        if (chatPollingInterval) clearInterval(chatPollingInterval);

        chatPollingInterval = setInterval(async () => {
            if (!currentUser || !currentUser.logged_in) return;

            try {
                const response = await fetch('../api/chat.php?action=poll_conversations');
                const result = await response.json();

                if (result.success) {
                    loadConversationsIntoWidget(result.conversations);
                }
            } catch (error) {
                console.error('Error al hacer polling de conversaciones:', error);
            }
        }, 5000);
    }

    function loadConversationsIntoWidget(conversations) {
        const chatList = document.getElementById('chat_list');
        if (!chatList) return;

        chatList.innerHTML = '';

        conversations.forEach(conv => {
            const convElement = document.createElement('div');
            convElement.className = 'conversation_item flex items-center gap-4 p-3 rounded-lg cursor-pointer transition-all hover:bg-gray-700';
            convElement.dataset.userid = conv.user_id;
            convElement.innerHTML = `
                <img src="${conv.profile_image || 'https://placehold.co/40x40/6a5acd/ffffff?text=${conv.full_name.charAt(0)}'}" alt="Perfil" class="w-10 h-10 rounded-full object-cover">
                <div class="flex-grow">
                    <p class="font-semibold text-white">${conv.full_name}</p>
                    <p class="text-sm text-gray-400">${conv.last_message || 'Inicia una conversación'}</p>
                </div>
                <span class="text-xs rounded-full px-3 py-1 ${conv.unread_messages > 0 ? 'bg-primary-blue text-white' : 'bg-gray-600 text-gray-300'}">${conv.unread_messages > 0 ? conv.unread_messages : ''}</span>
            `;

            convElement.addEventListener('click', () => {
                openChatWidgetWithUser(conv.user_id, conv.full_name);
            });

            chatList.appendChild(convElement);
        });
    }

    function openChatWidgetWithUser(userId, userName) {
        const chatWidget = document.getElementById('chat_widget');
        const chatTitle = document.getElementById('chat_title');
        const messagesContainer = document.getElementById('messages_container');
        const messageInput = document.getElementById('message_input');
        const sendMessageBtn = document.getElementById('send_message_btn');

        if (!chatWidget || !chatTitle || !messagesContainer || !messageInput || !sendMessageBtn) return;

        currentOpenChatUserId = userId;
        chatTitle.textContent = userName;
        messagesContainer.innerHTML = '';

        chatWidget.classList.remove('hidden');
        startChatPolling();

        loadMessages(userId, messagesContainer);

        sendMessageBtn.onclick = () => {
            const message = messageInput.value.trim();
            if (message) {
                sendMessage(userId, message);
                messageInput.value = '';
            }
        };
    }

    function sendMessage(userId, message) {
        fetch('../api/chat.php?action=send_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ receiver_id: userId, message_text: message })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                loadMessages(userId, document.getElementById('messages_container'));
            } else {
                console.error('Error al enviar el mensaje:', result.message);
            }
        })
        .catch(error => console.error('Error de conexión:', error));
    }

    function loadMessages(userId, container) {
        fetch(`../api/chat.php?action=get_messages&other_user_id=${userId}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    container.innerHTML = '';
                    result.messages.forEach(msg => {
                        container.appendChild(createMessageBubble(msg));
                    });
                    container.scrollTop = container.scrollHeight;
                } else {
                    console.error('Error al cargar los mensajes:', result.message);
                }
            })
            .catch(error => console.error('Error de conexión al cargar mensajes:', error));
    }

    function createMessageBubble(msg) {
        const bubble = document.createElement('div');
        bubble.className = `message_bubble p-3 rounded-lg mb-2 max-w-xs break-words ${msg.sender_id === currentUser.user_id ? 'bg-blue-500 text-white self-end' : 'bg-gray-200 text-gray-900'}`;
        bubble.textContent = msg.message;
        return bubble;
    }

    async function initMessagesPage() {
        const convListContainer = document.getElementById('conv-list-container');
        const chatWelcome = document.getElementById('chat-welcome');
        const chatConversation = document.getElementById('chat-conversation');
        const chatHeader = document.getElementById('chat-header');
        const chatMessages = document.getElementById('chat-messages');
        const sendMessageForm = document.getElementById('sendMessageForm');
        const messageInput = document.getElementById('message-input');
        let currentOpenChatUserId = null;
        let messagePollingInterval = null;

        if (!convListContainer) {
            console.error("El contenedor de conversaciones no se encontró.");
            return;
        }

        function createMessageBubble(msg) {
            const isMe = msg.sender_id == currentUser.user_id;
            const bubbleWrapper = document.createElement('div');
            bubbleWrapper.className = `flex w-full mt-2 space-x-3 max-w-xs ${isMe ? 'ml-auto justify-end' : 'justify-start'}`;
            bubbleWrapper.innerHTML = `
                <div>
                    <div class="p-3 rounded-l-lg rounded-br-lg ${isMe ? 'bg-primary-blue text-white' : 'bg-gray-300 text-gray-900'}">
                        <p class="text-sm">${msg.message_text}</p>
                    </div>
                    <span class="text-xs text-gray-400 leading-none">${msg.send_date ? new Date(msg.send_date).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) : ''}</span>
                </div>
            `;
            return bubbleWrapper;
        }

        async function loadMessages(otherUserId) {
            if (!otherUserId) return;
            try {
                const response = await fetch(`../api/chat.php?action=get_messages&other_user_id=${otherUserId}`);
                const result = await response.json();
                chatMessages.innerHTML = '';
                if (result.success) {
                    result.messages.forEach(msg => chatMessages.appendChild(createMessageBubble(msg)));
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            } catch (error) {
                console.error("Error al cargar mensajes:", error);
            }
        }

        async function openConversation(conv) {
            currentOpenChatUserId = conv.user_id;
            chatWelcome.classList.add('hidden');
            chatConversation.classList.remove('hidden');
            const profileImg = conv.profile_image_path && !conv.profile_image_path.includes('default')
                ? `../uploads/profile_images/${conv.profile_image_path}`
                : `https://placehold.co/40x40/6a5acd/ffffff?text=${conv.full_name.charAt(0)}`;
            chatHeader.innerHTML = `
                <img src="${profileImg}" alt="Perfil" class="w-10 h-10 rounded-full object-cover">
                <span class="font-bold text-lg">${conv.full_name}</span>
            `;
            if (messagePollingInterval) clearInterval(messagePollingInterval);
            await loadMessages(currentOpenChatUserId);
            messagePollingInterval = setInterval(() => loadMessages(currentOpenChatUserId), 3000);
        }

        function createConversationElement(conv) {
            const element = document.createElement('div');
            element.className = 'flex items-center gap-3 p-3 cursor-pointer hover:bg-white hover:bg-opacity-20 transition-colors border-b border-white border-opacity-10';
            const profileImg = conv.profile_image_path && !conv.profile_image_path.includes('default')
                ? `../uploads/profile_images/${conv.profile_image_path}`
                : `https://placehold.co/40x40/6a5acd/ffffff?text=${conv.full_name.charAt(0)}`;
            element.innerHTML = `
                <img src="${profileImg}" alt="Perfil" class="w-12 h-12 rounded-full object-cover">
                <div class="flex-grow overflow-hidden">
                    <p class="font-bold truncate">${conv.full_name}</p>
                    <p class="text-sm text-gray-300 truncate">${conv.message_text || 'Inicia la conversación'}</p>
                </div>
                ${conv.unread_count > 0 ? `<span class="bg-primary-blue text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">${conv.unread_count}</span>` : ''}
            `;
            element.addEventListener('click', () => openConversation(conv));
            return element;
        }

        async function loadConversationList() {
            try {
                convListContainer.innerHTML = '<p class="p-4 text-gray-300">Cargando...</p>';
                const response = await fetch('../api/chat.php?action=list_conversations');
                const result = await response.json();
                if (result.success && result.conversations.length > 0) {
                    convListContainer.innerHTML = '';
                    result.conversations.forEach(conv => convListContainer.appendChild(createConversationElement(conv)));
                } else {
                    convListContainer.innerHTML = '<p class="p-4 text-gray-300">No tienes conversaciones.</p>';
                }
            } catch (error) {
                console.error('Error al cargar la lista de conversaciones:', error);
                convListContainer.innerHTML = '<p class="p-4 text-red-400">Error al cargar la lista.</p>';
            }
        }

        sendMessageForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const messageText = messageInput.value.trim();
            if (messageText && currentOpenChatUserId) {
                const tempMessage = messageInput.value;
                messageInput.value = '';
                try {
                    await fetch('../api/chat.php?action=send_message', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ receiver_id: currentOpenChatUserId, message_text: tempMessage })
                    });
                    await loadMessages(currentOpenChatUserId);
                } catch (error) {
                    console.error("Error al enviar mensaje:", error);
                    messageInput.value = tempMessage;
                }
            }
        });

        loadConversationList();
        setInterval(loadConversationList, 5000);
    }

    function initMyApplicationsPage() {
        // Aquí va la lógica para mostrar las aplicaciones del usuario aspirante
        // Por ejemplo, puedes mostrar un mensaje temporal:
        const container = document.getElementById('my_applications_container');
        if (container) {
            container.innerHTML = '<p class="text-center text-gray-400">Aquí se mostrarán tus aplicaciones.</p>';
        }
    }

    // Reemplaza tu función initMyApplicationsPage por esta versión:
    async function initMyApplicationsPage() {
        console.log("Iniciando la página 'Mis Postulaciones'...");
        const appsContainer = document.getElementById('applications_list_container');
        const savedContainer = document.getElementById('saved_jobs_list_container');
        const loadingAppsSpinner = document.getElementById('loading_spinner_applications');
        const noAppsMessage = document.getElementById('no_applications_message');
        const loadingSavedSpinner = document.getElementById('loading_spinner_saved_jobs');
        const noSavedMessage = document.getElementById('no_saved_jobs_message');

        if (!appsContainer || !savedContainer) {
            console.error("Error: Faltan contenedores en my_applications.html");
            return;
        }

        loadingAppsSpinner.classList.remove('hidden');
        loadingSavedSpinner.classList.remove('hidden');

        // Cargar APLICACIONES
        try {
            const response = await fetch('../api/applications.php?action=list_my_applications');
            if (!response.ok) {
                throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
            }
            const result = await response.json();
            console.log("Respuesta de API (aplicaciones):", result);

            loadingAppsSpinner.classList.add('hidden');
            if (result.success && result.applications.length > 0) {
                appsContainer.innerHTML = '';
                result.applications.forEach(app => appsContainer.appendChild(createMyApplicationCard(app)));
            } else {
                noAppsMessage.classList.remove('hidden');
            }
        } catch (e) {
            console.error("FALLO al pedir aplicaciones:", e);
            loadingAppsSpinner.classList.add('hidden');
            noAppsMessage.textContent = 'Error al cargar tus aplicaciones.';
            noAppsMessage.classList.remove('hidden');
        }

        // Cargar EMPLEOS GUARDADOS
        try {
            const response = await fetch('../api/applications.php?action=list_saved_jobs');
            if (!response.ok) {
                throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
            }
            const result = await response.json();
            console.log("Respuesta de API (guardados):", result);

            loadingSavedSpinner.classList.add('hidden');
            if (result.success && result.saved_jobs.length > 0) {
                savedContainer.innerHTML = '';
                result.saved_jobs.forEach(job => savedContainer.appendChild(createSavedJobCard(job)));
            } else {
                noSavedMessage.classList.remove('hidden');
            }
        } catch (e) {
            console.error("FALLO al pedir empleos guardados:", e);
            loadingSavedSpinner.classList.add('hidden');
            noSavedMessage.textContent = 'Error al cargar empleos guardados.';
            noSavedMessage.classList.remove('hidden');
        }   
    }

    // Tarjeta para cada aplicación
    function createMyApplicationCard(app) {
        const card = document.createElement('div');
        card.className = 'bg-white bg-opacity-20 p-4 rounded-lg flex justify-between items-center transition-transform transform hover:scale-105';

        const statusClasses = { 
            pending: 'bg-yellow-500', 
            in_review: 'bg-blue-500',
            interview: 'bg-purple-500',
            accepted: 'bg-green-500', 
            rejected: 'bg-red-500' 
        };
        const statusTexts = { 
            pending: 'Pendiente', 
            in_review: 'En Revisión',
            interview: 'Entrevista',
            accepted: 'Aceptada', 
            rejected: 'Rechazada' 
        };

        card.innerHTML = `
            <div>
                <a href="job_detail.html?post_id=${app.post_id}" class="font-bold text-lg text-white hover:underline">${app.title}</a>
                <p class="text-gray-300">${app.company_name_posting || ''}</p>
            </div>
            <span class="px-3 py-1 text-sm font-semibold rounded-full text-white ${statusClasses[app.application_status] || 'bg-gray-400'}">
                ${statusTexts[app.application_status] || 'Desconocido'}
            </span>
        `;
        return card;
    }

    // Tarjeta para cada empleo guardado
    function createSavedJobCard(job) {
        const card = document.createElement('div');
        card.className = 'bg-white bg-opacity-20 p-4 rounded-lg flex justify-between items-center transition-transform transform hover:scale-105';
        card.innerHTML = `
            <div>
                <a href="job_detail.html?post_id=${job.post_id}" class="font-bold text-lg text-white hover:underline">${job.title}</a>
                <p class="text-gray-300">${job.company_name_posting || ''}</p>
            </div>
            <a href="job_detail.html?post_id=${job.post_id}" class="px-4 py-2 bg-primary-blue rounded-lg hover:bg-secondary-purple text-white font-semibold transition">
                Ver y Aplicar
            </a>
        `;
        return card;
    }

    main();
});
document.addEventListener("click", function (event) {
  if (event.target && event.target.classList.contains("start-chat-btn")) {
    const card = event.target.closest(".applicant-card");
    if (!card || !card.dataset.applicant) {
      console.error("No se encontró el data-applicant del aplicante.");
      return;
    }

    let applicantData;
    try {
      applicantData = JSON.parse(card.dataset.applicant);
      console.log("✅ Datos del aplicante:", applicantData);
    } catch (e) {
      console.error("❌ Error al parsear data-applicant:", e);
      return;
    }

    const receiverId = applicantData.applicant_id;
    const messageText = "Hola, gracias por postularte. ¿Podemos hablar sobre tu aplicación?";

    fetch("../api/chat.php?action=send_message", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        receiver_id: receiverId,
        message_text: messageText,
      }),
    })
      .then(async (response) => {
        if (!response.ok) {
          const errorText = await response.text();
          console.error("❌ Error al enviar mensaje:", errorText);
          alert("Hubo un error al contactar con el servidor.");
          return;
        }
        return response.json();
      })
      .then((data) => {
        if (data?.success) {
          console.log("✅ Mensaje enviado correctamente");
          window.location.href = "../pages/messages.html"; // Redirección al chat
        } else {
          alert("⚠️ Error al enviar mensaje: " + data?.message);
        }
      })
      .catch((err) => {
        console.error("❌ Error en la petición:", err);
        alert("No se pudo enviar el mensaje.");
      });
  }
});
