<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateUserIasDetails extends Migration
{
       /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('users')) {            
            if (!Schema::hasColumn('users', 'emp_id')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->integer('emp_id')->nullable();
                    $table->date('reg_date')->format('Y-m-d')->nullable(); // YMD format
                    $table->integer('dob')->format('Y-m-d')->nullable();
                    $table->integer('batch')->nullable(); 
                    $table->date('date_joining')->format('Y-m-d')->nullable();
                    $table->date('retired_date')->format('Y-m-d')->nullable(); 
                    $table->string('proof')->nullable();
                    $table->integer('membership_type');
                    $table->integer('payment_mode');
                    $table->integer('userid');
                    $table->integer('first_name');
                    $table->integer('last_name');
                });
            }            
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
}
