<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement(<<<'SQL'
            CREATE TABLE qr_codes_new (
                qr_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                order_id INTEGER NOT NULL,
                qr_code VARCHAR NOT NULL,
                type VARCHAR CHECK ("type" IN ('pickup-open', 'pickup-closed', 'delivery-open', 'delivery-closed')) NOT NULL DEFAULT 'pickup-open',
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY(order_id) REFERENCES service_orders(order_id)
            )
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO qr_codes_new (qr_id, order_id, qr_code, type, created_at, updated_at)
            SELECT
                qr_id,
                order_id,
                qr_code,
                CASE
                    WHEN type = 'Pickup' THEN 'pickup-open'
                    WHEN type = 'Delivery' THEN 'delivery-open'
                    ELSE type
                END,
                created_at,
                updated_at
            FROM qr_codes
        SQL);

        DB::statement('DROP TABLE qr_codes');
        DB::statement('ALTER TABLE qr_codes_new RENAME TO qr_codes');
        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement(<<<'SQL'
            CREATE TABLE qr_codes_old (
                qr_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                order_id INTEGER NOT NULL,
                qr_code VARCHAR NOT NULL,
                type VARCHAR CHECK ("type" IN ('Pickup', 'Delivery')) NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY(order_id) REFERENCES service_orders(order_id)
            )
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO qr_codes_old (qr_id, order_id, qr_code, type, created_at, updated_at)
            SELECT
                qr_id,
                order_id,
                qr_code,
                CASE
                    WHEN type IN ('pickup-open', 'pickup-closed') THEN 'Pickup'
                    WHEN type IN ('delivery-open', 'delivery-closed') THEN 'Delivery'
                    ELSE type
                END,
                created_at,
                updated_at
            FROM qr_codes
        SQL);

        DB::statement('DROP TABLE qr_codes');
        DB::statement('ALTER TABLE qr_codes_old RENAME TO qr_codes');
        DB::statement('PRAGMA foreign_keys = ON');
    }
};
