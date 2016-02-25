<?php
use Notadd\Foundation\Database\Schema\Blueprint;
use Notadd\Foundation\Database\Migrations\Migration;
class CreatePasswordResetsTable extends Migration {
    /**
     * Run the migrations.
     * @return void
     */
    public function up() {
        $this->schema->create('password_resets', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token')->index();
            $table->timestamp('created_at');
        });
    }
    /**
     * Reverse the migrations.
     * @return void
     */
    public function down() {
        $this->schema->drop('password_resets');
    }
}