<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $letterColumn = collect(Schema::getColumns('dispositions'))->firstWhere('name', 'letter_id');
        if (($letterColumn['nullable'] ?? true) === true) {
            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement("
            CREATE TABLE dispositions_rebuild (
                id integer primary key autoincrement not null,
                letter_id integer null,
                sender_name varchar not null,
                recipient_name varchar not null,
                instruction text not null,
                status varchar not null default 'Belum Dibaca',
                created_at datetime null,
                updated_at datetime null,
                disposition_recipient_id integer null,
                parent_id integer null,
                input_method varchar not null default 'Elektronik',
                input_by_name varchar null,
                input_by_role varchar null,
                scan_path varchar null,
                scan_original_name varchar null,
                scan_mime_type varchar null,
                scan_size integer null,
                foreign key(letter_id) references letters(id) on delete set null,
                foreign key(disposition_recipient_id) references disposition_recipients(id) on delete set null,
                foreign key(parent_id) references dispositions(id) on delete set null
            )
        ");
        DB::statement("
            INSERT INTO dispositions_rebuild (
                id,
                letter_id,
                sender_name,
                recipient_name,
                instruction,
                status,
                created_at,
                updated_at,
                disposition_recipient_id,
                parent_id,
                input_method,
                input_by_name,
                input_by_role,
                scan_path,
                scan_original_name,
                scan_mime_type,
                scan_size
            )
            SELECT
                id,
                letter_id,
                sender_name,
                recipient_name,
                instruction,
                status,
                created_at,
                updated_at,
                disposition_recipient_id,
                parent_id,
                input_method,
                input_by_name,
                input_by_role,
                scan_path,
                scan_original_name,
                scan_mime_type,
                scan_size
            FROM dispositions
        ");
        DB::statement('DROP TABLE dispositions');
        DB::statement('ALTER TABLE dispositions_rebuild RENAME TO dispositions');
        DB::statement('PRAGMA foreign_keys=ON');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
