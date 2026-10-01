<div class="grid sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">First Name</label>
        <input type="text" name="first_name" value="{{ old('first_name', $user->first_name ?? '') }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Last Name</label>
        <input type="text" name="last_name" value="{{ old('last_name', $user->last_name ?? '') }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Middle Name</label>
        <input type="text" name="middle_name" value="{{ old('middle_name', $user->middle_name ?? '') }}" class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Employee Number</label>
        <input type="text" name="employee_number" value="{{ old('employee_number', $user->employee_number ?? '') }}" class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Username</label>
        <input type="text" name="username" value="{{ old('username', $user->username ?? '') }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Password {{ isset($user) ? '(leave blank to keep)' : '' }}</label>
        <input type="password" name="password" {{ isset($user) ? '' : 'required' }} class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Role</label>
        <select name="role" required class="w-full rounded-lg border-slate-300">
            @foreach($roles as $role)
            <option value="{{ $role->value }}" @selected(old('role', $user->role->value ?? '') === $role->value)>{{ ucfirst($role->value) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select name="status" required class="w-full rounded-lg border-slate-300">
            @foreach(['active','inactive','archived'] as $s)
            <option value="{{ $s }}" @selected(old('status', $user->status->value ?? 'active') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
</div>
