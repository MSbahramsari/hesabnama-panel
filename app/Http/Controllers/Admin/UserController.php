<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();
        $users = User::query()
            ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        return view('admin.users.create', $this->formData());
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        return redirect()->route('admin.users.index')->with('success', "کاربر {$user->name} ساخته شد؛ رمز فقط از مسیر امن برای او ارسال شود.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', array_merge($this->formData(), compact('user')));
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if ($request->user()->is($user) && ($data['role'] !== UserRole::Admin->value || ! $data['is_active'])) {
            throw ValidationException::withMessages(['role' => 'نمی‌توانید دسترسی مدیریتی حساب فعال خودتان را حذف کنید.']);
        }

        if (blank($data['password'] ?? null)) {
            $data = Arr::except($data, ['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'اطلاعات کاربر به‌روزرسانی شد.');
    }

    /** @return array{roles: array<int, UserRole>, plans: array<int, Plan>, permissions: array<string, string>} */
    private function formData(): array
    {
        return [
            'roles' => UserRole::cases(),
            'plans' => Plan::cases(),
            'permissions' => ['customers' => 'مدیریت مشتریان', 'goods' => 'مدیریت کالا و خدمات', 'invoices' => 'مدیریت و ارسال فاکتورها'],
        ];
    }
}
