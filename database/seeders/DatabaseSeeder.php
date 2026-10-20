<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed 102,000 related dummy rows for the HR tables shown in the schema.
     * Counts: 10 cabang, 990 karyawan, 100,000 absensi, and 1,000 pengajuan_cuti.
     */
    public function run(): void
    {
        $faker = FakerFactory::create('id_ID');
        $faker->seed(20261019);

        DB::transaction(function () use ($faker): void {
            $branchIds = [];

            for ($i = 1; $i <= 10; $i++) {
                $name = sprintf('Cabang Dummy %02d', $i);
                $existing = DB::table('cabang')
                    ->where('nama_cabang', $name)
                    ->value('id_cabang');

                $branchIds[] = $existing ?? DB::table('cabang')->insertGetId([
                    'nama_cabang' => $name,
                    'alamat_cabang' => $faker->address(),
                    'status_operasional' => 'Aktif',
                ], 'id_cabang');
            }

            $positions = ['Staff', 'Admin', 'HRD', 'Supervisor', 'Kasir', 'Gudang'];
            $employeeNiks = [];
            $employeeRows = [];

            for ($i = 1; $i <= 990; $i++) {
                $nik = '999'.str_pad((string) $i, 13, '0', STR_PAD_LEFT);
                $employeeNiks[] = $nik;
                $employeeRows[] = [
                    'nik' => $nik,
                    'id_cabang' => $branchIds[($i - 1) % count($branchIds)],
                    'nama_lengkap' => $faker->name(),
                    'no_hp' => '08'.str_pad((string) (100000000 + $i), 10, '0', STR_PAD_LEFT),
                    'alamat' => $faker->address(),
                    'jabatan' => $positions[($i - 1) % count($positions)],
                    'gaji_pokok' => $faker->numberBetween(3500000, 15000000),
                    'sisa_kuota_cuti' => $faker->numberBetween(0, 12),
                    'is_active' => 1,
                ];
            }

            foreach (array_chunk($employeeRows, 500) as $chunk) {
                DB::table('karyawan')->upsert($chunk, ['nik'], [
                    'id_cabang', 'nama_lengkap', 'no_hp', 'alamat', 'jabatan',
                    'gaji_pokok', 'sisa_kuota_cuti', 'is_active',
                ]);
            }

            $baseDate = CarbonImmutable::create(2024, 1, 1);
            $attendanceRows = [];

            for ($i = 0; $i < 100000; $i++) {
                $employeeIndex = $i % count($employeeNiks);
                $day = intdiv($i, count($employeeNiks)) * 7 + ($employeeIndex % 5);
                $roll = $faker->numberBetween(1, 100);
                $status = match (true) {
                    $roll <= 78 => 'Hadir',
                    $roll <= 88 => 'Terlambat',
                    $roll <= 93 => 'Izin',
                    $roll <= 98 => 'Sakit',
                    default => 'Alpha',
                };
                $arrivalMinute = $status === 'Terlambat'
                    ? $faker->numberBetween(541, 600)
                    : $faker->numberBetween(420, 539);
                $departureMinute = $faker->numberBetween(960, 1080);

                $attendanceRows[] = [
                    'nik' => $employeeNiks[$employeeIndex],
                    'tanggal' => $baseDate->addDays($day)->toDateString(),
                    'jam_masuk' => $status === 'Alpha' ? '09:00:00' : sprintf('%02d:%02d:00', intdiv($arrivalMinute, 60), $arrivalMinute % 60),
                    'jam_pulang' => $status === 'Alpha' ? '17:00:00' : sprintf('%02d:%02d:00', intdiv($departureMinute, 60), $departureMinute % 60),
                    'status_kehadiran' => $status,
                    'keterangan_susulan' => $status === 'Terlambat' ? 'Konfirmasi keterlambatan' : '',
                ];

                if (count($attendanceRows) === 500) {
                    DB::table('absensi')->upsert($attendanceRows, ['nik', 'tanggal'], [
                        'jam_masuk', 'jam_pulang', 'status_kehadiran', 'keterangan_susulan',
                    ]);
                    $attendanceRows = [];
                }
            }

            if ($attendanceRows !== []) {
                DB::table('absensi')->upsert($attendanceRows, ['nik', 'tanggal'], [
                    'jam_masuk', 'jam_pulang', 'status_kehadiran', 'keterangan_susulan',
                ]);
            }

            $leaveReasons = [
                'Keperluan keluarga',
                'Urusan pribadi',
                'Acara keluarga',
                'Istirahat dan pemulihan',
                'Keperluan penting di luar kota',
            ];
            $leaveStatuses = ['Menunggu', 'Disetujui', 'Ditolak'];
            for ($i = 0; $i < 1000; $i++) {
                $requesterIndex = $i % count($employeeNiks);
                $approverIndex = ($requesterIndex + 1) % count($employeeNiks);
                $start = $baseDate->addDays($i % 900);

                DB::table('pengajuan_cuti')->updateOrInsert(
                    [
                        'nik' => $employeeNiks[$requesterIndex],
                        'tanggal_mulai' => $start->toDateString(),
                    ],
                    [
                        'tanggal_selesai' => $start->addDays($faker->numberBetween(0, 4))->toDateString(),
                        'alasan' => $leaveReasons[$i % count($leaveReasons)],
                        'status_pengajuan' => $leaveStatuses[$i % count($leaveStatuses)],
                        'disetujui_oleh' => $employeeNiks[$approverIndex],
                    ],
                );
            }
        });
    }
}