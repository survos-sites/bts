<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260901140601 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // term is babel_term's predecessor. SQLite index names are global to the
        // database, not scoped to a table, so term_term_set_code_uq and
        // term_term_set_path_uq have to be dropped with it before babel_term can
        // declare its own. make:migration emitted the drop last, which fails.
        $this->addSql('DROP TABLE term');
        $this->addSql('CREATE TABLE babel_term (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(120) NOT NULL, path VARCHAR(512) NOT NULL, label_code VARCHAR(64) NOT NULL, description_code VARCHAR(64) DEFAULT NULL, rules CLOB NOT NULL, meta CLOB NOT NULL, enabled BOOLEAN NOT NULL, sort INTEGER DEFAULT NULL, term_set_id INTEGER NOT NULL, parent_id INTEGER DEFAULT NULL, CONSTRAINT FK_77FE42F2F3AD3475 FOREIGN KEY (term_set_id) REFERENCES term_set (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_77FE42F2727ACA70 FOREIGN KEY (parent_id) REFERENCES babel_term (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_77FE42F2F3AD3475 ON babel_term (term_set_id)');
        $this->addSql('CREATE INDEX IDX_77FE42F2727ACA70 ON babel_term (parent_id)');
        $this->addSql('CREATE UNIQUE INDEX term_term_set_code_uq ON babel_term (term_set_id, code)');
        $this->addSql('CREATE UNIQUE INDEX term_term_set_path_uq ON babel_term (term_set_id, path)');
        $this->addSql('CREATE TABLE index_info (index_name VARCHAR(255) NOT NULL, last_indexed DATETIME DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, document_count INTEGER NOT NULL, settings CLOB NOT NULL, task_id VARCHAR(255) DEFAULT NULL, primary_key VARCHAR(255) NOT NULL, batch_id VARCHAR(255) DEFAULT NULL, status VARCHAR(20) DEFAULT NULL, label VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, aggregator VARCHAR(255) DEFAULT NULL, institution VARCHAR(255) DEFAULT NULL, country VARCHAR(255) DEFAULT NULL, locale VARCHAR(255) DEFAULT NULL, PRIMARY KEY (index_name))');
        $this->addSql('CREATE TABLE media (id VARCHAR(32) NOT NULL, status VARCHAR(255) NOT NULL, provider VARCHAR(100) DEFAULT NULL, external_id VARCHAR(255) DEFAULT NULL, external_url CLOB DEFAULT NULL, raw_data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, published_at DATETIME DEFAULT NULL, thumbnail_url CLOB DEFAULT NULL, location CLOB DEFAULT NULL, tags CLOB NOT NULL, width INTEGER DEFAULT NULL, height INTEGER DEFAULT NULL, duration INTEGER DEFAULT NULL, file_size BIGINT DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, title CLOB DEFAULT NULL, description CLOB DEFAULT NULL, type VARCHAR(255) NOT NULL, camera VARCHAR(255) DEFAULT NULL, exif_data CLOB DEFAULT NULL, taken_at DATETIME DEFAULT NULL, view_count INTEGER DEFAULT NULL, like_count INTEGER DEFAULT NULL, chapters CLOB DEFAULT NULL, subtitles CLOB DEFAULT NULL, artist VARCHAR(255) DEFAULT NULL, album VARCHAR(255) DEFAULT NULL, bitrate INTEGER DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TEMPORARY TABLE __temp__messenger_messages AS SELECT id, body, headers, queue_name, created_at, available_at, delivered_at FROM messenger_messages');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('INSERT INTO messenger_messages (id, body, headers, queue_name, created_at, available_at, delivered_at) SELECT id, body, headers, queue_name, created_at, available_at, delivered_at FROM __temp__messenger_messages');
        $this->addSql('DROP TABLE __temp__messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE term (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(120) NOT NULL COLLATE "BINARY", path VARCHAR(512) NOT NULL COLLATE "BINARY", label_code VARCHAR(64) NOT NULL COLLATE "BINARY", description_code VARCHAR(64) DEFAULT NULL COLLATE "BINARY", rules CLOB NOT NULL COLLATE "BINARY", meta CLOB NOT NULL COLLATE "BINARY", enabled BOOLEAN NOT NULL, sort INTEGER DEFAULT NULL, term_set_id INTEGER NOT NULL, parent_id INTEGER DEFAULT NULL, CONSTRAINT FK_A50FE78DF3AD3475 FOREIGN KEY (term_set_id) REFERENCES term_set (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A50FE78D727ACA70 FOREIGN KEY (parent_id) REFERENCES term (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX term_term_set_path_uq ON term (term_set_id, path)');
        $this->addSql('CREATE UNIQUE INDEX term_term_set_code_uq ON term (term_set_id, code)');
        $this->addSql('CREATE INDEX IDX_A50FE78D727ACA70 ON term (parent_id)');
        $this->addSql('CREATE INDEX IDX_A50FE78DF3AD3475 ON term (term_set_id)');
        $this->addSql('DROP TABLE babel_term');
        $this->addSql('DROP TABLE index_info');
        $this->addSql('DROP TABLE media');
        $this->addSql('CREATE TEMPORARY TABLE __temp__messenger_messages AS SELECT id, body, headers, queue_name, created_at, available_at, delivered_at FROM messenger_messages');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('INSERT INTO messenger_messages (id, body, headers, queue_name, created_at, available_at, delivered_at) SELECT id, body, headers, queue_name, created_at, available_at, delivered_at FROM __temp__messenger_messages');
        $this->addSql('DROP TABLE __temp__messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
    }
}
