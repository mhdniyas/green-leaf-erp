<?php

declare(strict_types=1);

namespace App\DTOs\Admin;

use Illuminate\Http\Request;

final readonly class UserData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password,
        public ?int $shopId,
        public array $roles,
        public array $warehouseIds,
        public ?int $defaultWarehouseId,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->filled('password') ? $request->string('password')->toString() : null,
            shopId: $request->filled('shop_id') ? (int) $request->integer('shop_id') : null,
            roles: $request->input('roles', []),
            warehouseIds: array_values(array_map('intval', $request->input('warehouse_ids', []))),
            defaultWarehouseId: $request->filled('default_warehouse_id')
                ? (int) $request->integer('default_warehouse_id')
                : null,
        );
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'shop_id' => $this->shopId,
        ];

        if ($this->password !== null) {
            $data['password'] = $this->password;
        }

        return $data;
    }
}
