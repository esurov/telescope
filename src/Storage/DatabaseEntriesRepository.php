<?php

namespace Laravel\Telescope\Storage;

use Laravel\Telescope\Entry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Laravel\Telescope\Contracts\EntriesRepository as Contract;

class DatabaseEntriesRepository implements Contract
{
    /**
     * Find the entry with the given ID.
     *
     * @param  mixed  $id
     * @return mixed
     */
    public function find($id)
    {
        $entry = DB::table('telescope_entries')
                    ->whereId($id)
                    ->first();

        abort_unless($entry, 404);

        return tap($entry, function ($entry) {
            $entry->content = json_decode($entry->content);
        });
    }

    /**
     * Return all the entries of a given type.
     *
     * @param  int  $type
     * @param  array  $options
     * @return \Illuminate\Support\Collection
     */
    public function get($type, $options = [])
    {
        return DB::table('telescope_entries')
            ->when($type, function ($q, $value) {
                return $q->where('type', $value);
            })
            ->when($options['before'] ?? false, function ($q, $value) {
                return $q->where('id', '<', $value);
            })
            ->when($options['tag'] ?? false, function ($q, $value) {
                $records = DB::table('telescope_entries_tags')->whereTag($value)->pluck('entry_id')->toArray();

                return $q->whereIn('id', $records);
            })
            ->when($options['batch_id'] ?? false, function ($q, $value) {
                return $q->where('batch_id', $value);
            })
            ->take($options['take'] ?? 50)
            ->orderByDesc('id')
            ->get()
            ->each(function ($entry) {
                $entry->content = json_decode($entry->content);
            });
    }

    /**
     * Store the given array of entries.
     *
     * @param  \Illuminate\Support\Collection  $entries
     * @return mixed
     */
    public function store(Collection $entries)
    {
        $entries->each(function (Entry $entry) {
            $this->storeTags(DB::table('telescope_entries')->insertGetId(
                $entry->toArray()
            ), $entry);
        });
    }

    /**
     * Store the tags for the given entry.
     *
     * @param  int  $entryId
     * @param  \Laravel\Telescope\Entry  $entry
     * @return void
     */
    protected function storeTags($entryId, Entry $entry)
    {
        DB::table('telescope_entries_tags')->insert(collect($entry->tags)->map(function ($tag) use ($entryId) {
            return [
                'entry_id' => $entryId,
                'tag' => $tag,
            ];
        })->toArray());
    }
}
