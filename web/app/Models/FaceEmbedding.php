<?php

namespace App\Models;

use Database\Factories\FaceEmbeddingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'pose', 'model', 'embedding', 'quality_score'])]
class FaceEmbedding extends Model
{
    /** @use HasFactory<FaceEmbeddingFactory> */
    use HasFactory;

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'quality_score' => 'float',
        ];
    }
}
