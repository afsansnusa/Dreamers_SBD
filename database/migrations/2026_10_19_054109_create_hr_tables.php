<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cabang', function (Blueprint $table): void {
            $table->id('id_cabang');
            $table->string('nama_cabang', 120);
            $table->text('alamat_cabang');
            $table->string('status_operasional', 30)->default('Aktif');
        });

        Schema::create('karyawan', function (Blueprint $table): void {
            $table->string('nik', 16)->primary();
            $table->unsignedBigInteger('id_cabang');
            $table->string('nama_lengkap', 150);
            $table->string('no_hp', 20);
            $table->text('alamat');
            $table->string('jabatan', 100);
            $table->decimal('gaji_pokok', 12, 2);
            $table->unsignedTinyInteger('sisa_kuota_cuti')->default(12);
            $table->boolean('is_active')->default(true);

            $table->foreign('id_cabang')
                ->references('id_cabang')
                ->on('cabang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('absensi', function (Blueprint $table): void {
            $table->id('id_absensi');
            $table->string('nik', 16);
            $table->date('tanggal');
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->string('status_kehadiran', 30);
            $table->string('keterangan_susulan')->default('');

            $table->unique(['nik', 'tanggal']);
            $table->foreign('nik')
                ->references('nik')
                ->on('karyawan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('pengajuan_cuti', function (Blueprint $table): void {
            $table->id('id_cuti');
            $table->string('nik', 16);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('status_pengajuan', 30)->default('Menunggu');
            $table->string('disetujui_oleh', 16)->nullable();

            $table->index(['nik', 'tanggal_mulai', 'tanggal_selesai']);
            $table->foreign('nik')
                ->references('nik')
                ->on('karyawan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('disetujui_oleh')
                ->references('nik')
                ->on('karyawan')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_cuti');
        Schema::dropIfExists('absensi');
        Schema::dropIfExists('karyawan');
        Schema::dropIfExists('cabang');
    }
};
