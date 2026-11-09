USE website_backup_dev;
CREATE TABLE IF NOT EXISTS demo_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO demo_notes (title) VALUES ('Première note de démonstration'), ('Tester la sauvegarde et l’export SQL');
