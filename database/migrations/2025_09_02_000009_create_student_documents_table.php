<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 11 (student documents). Files are stored on the
     * "secure_documents" disk (config/filesystems.php, private/non-public)
     * and served only through an authorized controller route - never a
     * public storage URL.
     */
    public function up(): void
    {
        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('document_type', [
                'birth_certificate', 'national_id', 'transfer_certificate', 'photo', 'other',
            ])->default('other');
            $table->string('title');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};
