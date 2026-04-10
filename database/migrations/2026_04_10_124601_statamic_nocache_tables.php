<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class StatamicNocacheTables extends Migration
{
    public function up()
    {
        Schema::connection(
            config('statamic.static_caching.nocache_db_connection')
        )->create('nocache_regions', function (Blueprint $table) {
            $table->string('key')->index()->primary();
            $table->string('url')->index();
            $table->longText('region');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection(
            config('statamic.static_caching.nocache_db_connection')
        )->dropIfExists('nocache_regions');
    }
}
