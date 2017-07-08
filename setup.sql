CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `type` INTEGER NOT NULL,
  `date_occured` DATE NOT NULL,
  `text` TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
  `id` INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(255) UNIQUE NOT NULL,
  `email` VARCHAR(255) UNIQUE NOT NULL,
  `role` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NULL,
  `apikey` VARCHAR(32) NOT NULL
);

/*
 * User role ref:
 * 0 - Guest
 * 1 - Member
 * 2 - Admin
 */

CREATE TABLE IF NOT EXISTS `images`(
  `id` INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `file_name` TEXT NOT NULL,
  `date_uploaded` DATETIME NOT NULL,
  `uploader` INTEGER NOT NULL,
  FOREIGN KEY (uploader) REFERENCES users(id)
);
-- pass: spideynn
INSERT INTO users (username, email, role, password, apikey) VALUES ("spideynn", "spideynn@gmail.com", 2, "$2y$10$3q4UJmyQ49Y8KNElt/gvBe39Ns/y4MTlwylUf2rycwv2ZY1U8bAiu", "23bcc730e746a39cb1bd77606c484c30");
