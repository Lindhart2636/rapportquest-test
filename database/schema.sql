-- RapportQuest Database Schema
-- MySQL 8+
-- Created: 2026-06-02

CREATE DATABASE IF NOT EXISTS rapportquest
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE rapportquest;

-- -------------------------------------------------------
-- reports
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename      VARCHAR(255)  NOT NULL,
    original_name VARCHAR(255)  NOT NULL,
    file_path     VARCHAR(512)  NOT NULL,
    file_size     INT UNSIGNED  NOT NULL DEFAULT 0,
    status        ENUM('pending','processing','ready','error') NOT NULL DEFAULT 'pending',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at    DATETIME      NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- report_sections
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS report_sections (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id    INT UNSIGNED NOT NULL,
    section_type VARCHAR(64)  NOT NULL,
    title        VARCHAR(255) NULL,
    content      LONGTEXT     NOT NULL,
    position     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sections_report
        FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- concepts
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS concepts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term       VARCHAR(255) NOT NULL,
    category   VARCHAR(64)  NOT NULL,
    weight     TINYINT UNSIGNED NOT NULL DEFAULT 5,
    synonyms   JSON         NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_concepts_term (term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- quiz_sets
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_sets (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id       INT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    total_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quiz_sets_report
        FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- quiz_questions
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_questions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_set_id    INT UNSIGNED NOT NULL,
    question_text  TEXT         NOT NULL,
    correct_answer TEXT         NOT NULL,
    distractors    JSON         NOT NULL,
    concept_id     INT UNSIGNED NULL,
    points         SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quiz_q_set
        FOREIGN KEY (quiz_set_id) REFERENCES quiz_sets(id) ON DELETE CASCADE,
    CONSTRAINT fk_quiz_q_concept
        FOREIGN KEY (concept_id) REFERENCES concepts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- cloze_sets
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS cloze_sets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id  INT UNSIGNED NOT NULL,
    title      VARCHAR(255) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cloze_sets_report
        FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- cloze_questions
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS cloze_questions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cloze_set_id      INT UNSIGNED NOT NULL,
    original_sentence TEXT         NOT NULL,
    blanked_sentence  TEXT         NOT NULL,
    answer            VARCHAR(255) NOT NULL,
    concept_id        INT UNSIGNED NULL,
    points            SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cloze_q_set
        FOREIGN KEY (cloze_set_id) REFERENCES cloze_sets(id) ON DELETE CASCADE,
    CONSTRAINT fk_cloze_q_concept
        FOREIGN KEY (concept_id) REFERENCES concepts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- boss_battles
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS boss_battles (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id     INT UNSIGNED NOT NULL,
    question_text TEXT         NOT NULL,
    model_answer  TEXT         NOT NULL,
    keywords      JSON         NOT NULL,
    points        SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_boss_report
        FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- progress
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS progress (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id    VARCHAR(128) NOT NULL,
    xp            INT UNSIGNED NOT NULL DEFAULT 0,
    level         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    streak        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    avatar        VARCHAR(120)  NOT NULL DEFAULT '',
    last_activity DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_progress_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- badges
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS badges (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(128) NOT NULL,
    badge_type VARCHAR(64)  NOT NULL,
    earned_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_badges_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
