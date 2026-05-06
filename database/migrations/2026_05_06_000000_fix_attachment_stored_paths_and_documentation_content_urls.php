<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_URL = 'https://checkmate-ysa.demo.airmedia.tech/storage/';

    public function up(): void
    {
        DB::table('attachments')
            ->where('stored_path', 'like', self::OLD_URL.'%')
            ->orderBy('id')
            ->chunk(100, function ($attachments) {
                foreach ($attachments as $attachment) {
                    DB::table('attachments')
                        ->where('id', $attachment->id)
                        ->update([
                            'stored_path' => str_replace(self::OLD_URL, '', $attachment->stored_path),
                        ]);
                }
            });

        DB::table('documentations')
            ->whereNotNull('content')
            ->where('content', 'like', '%'.self::OLD_URL.'%')
            ->orderBy('id')
            ->chunk(100, function ($docs) {
                foreach ($docs as $doc) {
                    DB::table('documentations')
                        ->where('id', $doc->id)
                        ->update([
                            'content' => str_replace(self::OLD_URL, '/storage/', $doc->content),
                        ]);
                }
            });
    }

    public function down(): void {}
};
