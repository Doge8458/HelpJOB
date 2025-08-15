-- helpjob.sql

-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS HelpJOB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Usar la base de datos recién creada
USE HelpJOB;

-- Tabla de Usuarios
CREATE TABLE IF NOT EXISTS Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL, -- Almacenar el hash de la contraseña
    phone_number VARCHAR(20),
    location VARCHAR(255), -- Ej. "Pachuca de Soto, Mexico"
    user_type ENUM('applicant', 'employer', 'company') NOT NULL, -- AÑADIDO 'company'
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    profile_image_path VARCHAR(255) DEFAULT 'assets/images/default_profile.png', -- Ruta a la imagen de perfil
    cv_pdf_path VARCHAR(255) DEFAULT NULL, -- Ruta al CV (solo para aspirantes)
    bio TEXT, -- Biografía del usuario o de la empresa
    work_experience TEXT, -- Experiencia laboral (solo para aspirantes)
    skills TEXT, -- Habilidades (ej. "JavaScript, React, Python", solo para aspirantes)
    company_name VARCHAR(255), -- Nombre de la empresa (solo para empleadores y empresas)
    company_role VARCHAR(255) -- Cargo en la empresa (solo para empleadores y empresas)
);

-- Tabla de Publicaciones de Empleo
CREATE TABLE IF NOT EXISTS JobPostings (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    employer_id INT NOT NULL, -- Ahora este ID puede ser un 'employer' o una 'company'
    title VARCHAR(255) NOT NULL,
    company_name_posting VARCHAR(255) NOT NULL, -- Denormalizado para fácil acceso
    employer_name_posting VARCHAR(255) NOT NULL, -- Denormalizado (Puede ser el nombre del contacto o de la empresa principal)
    description TEXT NOT NULL,
    requirements TEXT,
    benefits TEXT,
    job_location_text VARCHAR(255) NOT NULL, -- Ej. "Pachuca de Soto, Mexico"
    latitude DECIMAL(10, 8), -- Para OpenStreetMap
    longitude DECIMAL(11, 8), -- Para OpenStreetMap
    job_type ENUM('Full-time', 'Part-time', 'Contract', 'Temporary', 'Internship', 'Other') NOT NULL, -- 'Tiempo completo', 'Medio tiempo', 'Por proyecto', etc.
    salary VARCHAR(100), -- Ej. "$45,000 - $60,000 MXN/mes"
    application_count INT DEFAULT 0, -- Contador de aplicaciones
    post_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    category ENUM('Technology', 'Design', 'Marketing', 'Sales', 'Human Resources', 'Finance', 'Other') NOT NULL, -- Categorías de empleo
    post_status ENUM('active', 'assigned', 'expired_visible', 'expired_hidden') DEFAULT 'active', -- 'activa', 'asignada', 'expirada_visible', 'expirada_oculta'
    assigned_applicant_id INT DEFAULT NULL, -- ID del aspirante asignado
    assignment_date DATETIME DEFAULT NULL, -- Fecha de asignación
    expiration_date_visible DATETIME DEFAULT NULL, -- Fecha hasta la que es visible tras asignación
    FOREIGN KEY (employer_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_applicant_id) REFERENCES Users(user_id) ON DELETE SET NULL
);

-- Tabla de Aplicaciones
CREATE TABLE IF NOT EXISTS Applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    post_id INT NOT NULL,
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    application_status ENUM('pending', 'in_review', 'interview', 'accepted', 'rejected') DEFAULT 'pending', -- 'pendiente', 'en_revision', etc.
    applicant_message TEXT,
    FOREIGN KEY (applicant_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES JobPostings(post_id) ON DELETE CASCADE,
    UNIQUE (applicant_id, post_id) -- Un aspirante solo puede aplicar una vez por publicación
);

-- Tabla de Comentarios
CREATE TABLE IF NOT EXISTS Comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    comment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES JobPostings(post_id) ON DELETE CASCADE
);

-- Tabla de Calificaciones
CREATE TABLE IF NOT EXISTS Ratings (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    score INT NOT NULL CHECK (score >= 1 AND score <= 5), -- Puntuación de 1 a 5 estrellas
    rating_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES JobPostings(post_id) ON DELETE CASCADE,
    UNIQUE (user_id, post_id) -- Un usuario solo puede calificar una vez por publicación
);

-- Tabla de Publicaciones Guardadas
CREATE TABLE IF NOT EXISTS SavedPosts (
    saved_id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    post_id INT NOT NULL,
    save_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES JobPostings(post_id) ON DELETE CASCADE,
    UNIQUE (applicant_id, post_id) -- Un aspirante solo puede guardar una publicación una vez
);

-- Tabla de Mensajes de Chat
CREATE TABLE IF NOT EXISTS ChatMessages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_text TEXT NOT NULL,
    send_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Índices para mejorar el rendimiento de las búsquedas
CREATE INDEX idx_user_email ON Users (email);

-- INDICE CORREGIDO: Se indexa el título completo y los primeros 255 caracteres de la descripción.
-- Esto resuelve el error "Declaración de clave demasiado larga" para las columnas TEXT/VARCHAR largas.
CREATE INDEX idx_job_title_description ON JobPostings (title, description(255));

CREATE INDEX idx_job_location ON JobPostings (job_location_text);
CREATE INDEX idx_job_category ON JobPostings (category);
CREATE INDEX idx_application_status ON Applications (application_status);
CREATE INDEX idx_comment_post_id ON Comments (post_id);
CREATE INDEX idx_rating_post_id ON Ratings (post_id);
CREATE INDEX idx_chat_participants ON ChatMessages (sender_id, receiver_id);