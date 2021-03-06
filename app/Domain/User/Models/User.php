<?php

namespace App\Domain\User\Models;

use App\Domain\Event\Models\Attendee;
use App\Domain\Event\Models\Event;
use App\Domain\Review\Interfaces\Reviewable;
use App\Domain\Review\Models\Review;
use App\Domain\Shared\Models\Concerns\HasCountry;
use App\Domain\Shared\Models\Concerns\HasSlug;
use App\Domain\Slot\Models\Slot;
use App\Domain\Slot\Models\SlotOwnershipClaim;
use App\Domain\Slot\Models\Speaker;
use App\Domain\User\Models\Concerns\PresentsUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\PersonalDataExport\ExportsPersonalData;
use Spatie\PersonalDataExport\PersonalDataSelection;

class User extends Authenticatable implements MustVerifyEmail, ExportsPersonalData
{
    use Notifiable, HasSlug, HasCountry, PresentsUser;

    protected $fillable = [
        'name', 'email', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'admin' => 'bool',
        'can_publish_events_immediately' => 'bool',
    ];

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'organizers');
    }

    public function slots(): BelongsToMany
    {
        return $this->belongsToMany(Slot::class, 'speakers');
    }

    public function organizes(Event $event): bool
    {
        return $event->organizingUsers->contains(function (User $organizingUser) {
            return $organizingUser->id === $this->id;
        });
    }

    public function isSpeaker(Slot $slot): bool
    {
        return $slot->speakers->contains(function (Speaker $speaker) {
            return $speaker->user->id === $this->id;
        });
    }

    public function isClaimingSlot(Slot $slot): bool
    {
        return SlotOwnershipClaim::query()
            ->where('user_id', $this->id)
            ->where('slot_id', $slot->id)
            ->exists();
    }

    public function attendedEvents(): HasManyThrough
    {
        return $this->hasManyThrough(Event::class, Attendee::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function attended(Event $event): bool
    {
        return Attendee::query()
                ->where([
                    'user_id' => $this->id,
                    'event_id' => $event->id,
                ])
                ->count() > 0;
    }

    public function organisesEvents(): bool
    {
        return $this->events()->count() > 0;
    }

    public function speaksAtEvents(): bool
    {
        return $this->slots()->count() > 0;
    }

    public function attendsEvents(): bool
    {
        return $this->attendees()->count() > 0;
    }

    public function markEmailAsUnverified()
    {
        $this->email_verified_at = null;

        $this->save();

        return $this;
    }

    public function hasReviewed(Reviewable $reviewable)
    {
        return $reviewable->reviews()->where('user_id', $this->id)->exists();
    }

    public function scopeAdmin(Builder $query)
    {
        $query->where('admin', 1);
    }

    public function selectPersonalData(PersonalDataSelection $personalDataSelection): void
    {
        $personalDataSelection->add('user.json', $this->attributesToArray());
    }

    public function personalDataExportName(): string
    {
        $userName = Str::slug($this->name);

        return "personal-data-{$userName}.zip";
    }
}
