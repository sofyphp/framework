<?php

declare(strict_types=1);

use Sofy\Database\Schema\Blueprint;
use Sofy\Database\Schema\Schema;

/**
 * Portable inverted index for Sofy\Search\Drivers\DatabaseDriver. One row per
 * (index, document, term). Driver-agnostic — no engine-specific full-text DDL.
 */
return new class {
    public function up(): void
    {
        Schema::create('search_index', function (Blueprint $table) {
            $table->id();
            $table->string('index_name');
            $table->string('doc_id');
            $table->string('term');
            $table->float('weight');
            // term lookup during a query, and per-document replace on re-index.
            $table->index(['index_name', 'term']);
            $table->index(['index_name', 'doc_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_index');
    }
};
