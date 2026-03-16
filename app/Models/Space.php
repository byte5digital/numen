<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $default_locale
 * @property array|null $settings
 * @property array|null $api_config
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentType> $contentTypes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Content> $contents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Persona> $personas
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentPipeline> $pipelines
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentBrief> $briefs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ApiKey> $apiKeys
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Webhook> $webhooks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Vocabulary> $vocabularies
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SpaceLocale> $locales
 */
class Space extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'slug', 'description', 'default_locale', 'settings', 'api_config'];

    protected $casts = [
        'settings' => 'array',
        // api_config is handled via encrypted accessors/mutators below
    ];

    /**
     * Decrypt api_config when reading.
     * Stored as an encrypted JSON string; returned as an array.
     *
     * @return array<string, mixed>|null
     */
    public function getApiConfigAttribute(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $decrypted = Crypt::decryptString($value);

        return json_decode($decrypted, true);
    }

    /**
     * Encrypt api_config when writing.
     *
     * @param  array<string, mixed>|null  $value
     */
    public function setApiConfigAttribute(?array $value): void
    {
        if ($value === null) {
            $this->attributes['api_config'] = null;

            return;
        }

        $this->attributes['api_config'] = Crypt::encryptString(json_encode($value));
    }

    public function contentTypes(): HasMany
    {
        return $this->hasMany(ContentType::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(ContentPipeline::class);
    }

    public function briefs(): HasMany
    {
        return $this->hasMany(ContentBrief::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    /** @return HasMany<Vocabulary, $this> */
    public function vocabularies(): HasMany
    {
        return $this->hasMany(Vocabulary::class)->orderBy('sort_order');
    }

    /** @return HasMany<SpaceLocale, $this> */
    public function locales(): HasMany
    {
        return $this->hasMany(SpaceLocale::class);
    }
}
