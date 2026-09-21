<?php

namespace App\Support;

use RuntimeException;

/** Dilempar service saat opsi "lewati siswa tanpa data" aktif dan siswa tidak punya data apa pun. */
class ReportKosongException extends RuntimeException
{
}