<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository
{
    abstract protected function model(): string;

    protected function query(): Builder
    {
        return $this->model()::query();
    }

    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function findByPublicId(string $publicId): ?Model
    {
        return $this->query()->where('public_id', $publicId)->first();
    }

    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    public function update(int $id, array $data): Model
    {
        $model = $this->findOrFail($id);
        $model->update($data);

        return $model->fresh();
    }

    public function delete(int $id): bool
    {
        return (bool) $this->findOrFail($id)->delete();
    }

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage);
    }
}