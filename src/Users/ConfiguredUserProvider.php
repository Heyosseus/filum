<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Users;

use Heyosseus\Filum\Contracts\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The default answer to "who are the admins, and what are they called?",
 * assembled from config rather than from assumptions about your schema.
 */
final readonly class ConfiguredUserProvider implements UserProvider
{
    public function __construct(private Repository $config) {}

    public function name(Authenticatable $user): string
    {
        $column = $this->stringConfig('filum.users.name_column', 'name');

        $name = $user instanceof Model ? $user->getAttribute($column) : null;

        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        // A user with no name is still someone you might need to message.
        $email = $user instanceof Model ? $user->getAttribute('email') : null;

        if (is_string($email) && trim($email) !== '') {
            return $email;
        }

        return '#'.$this->id($user);
    }

    public function avatar(Authenticatable $user): ?string
    {
        $column = $this->config->get('filum.users.avatar_column');

        if (! is_string($column) || $column === '') {
            return null;
        }

        $avatar = $user instanceof Model ? $user->getAttribute($column) : null;

        return is_string($avatar) && $avatar !== '' ? $avatar : null;
    }

    public function chattable(Authenticatable $except): Collection
    {
        $model = $this->model();

        /** @var Collection<int, Authenticatable> $users */
        $users = $model::query()
            ->whereKeyNot($except->getAuthIdentifier())
            ->orderBy($this->stringConfig('filum.users.name_column', 'name'))
            ->get();

        return $users;
    }

    public function find(int|string $id): ?Authenticatable
    {
        $model = $this->model();

        $user = $model::query()->whereKey($id)->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    public function id(Authenticatable $user): int|string
    {
        $id = $user->getAuthIdentifier();

        if (is_int($id) || is_string($id)) {
            return $id;
        }

        throw new RuntimeException('The user identifier must be an int or a string.');
    }

    /**
     * @return class-string<Model>
     */
    private function model(): string
    {
        $model = $this->config->get('filum.users.model');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw new RuntimeException('filum.users.model must name an Eloquent model class.');
        }

        return $model;
    }

    private function stringConfig(string $key, string $fallback): string
    {
        $value = $this->config->get($key);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
