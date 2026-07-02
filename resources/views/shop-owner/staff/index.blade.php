@extends('shop-owner.layouts.app')

@section('title', 'Staff')
@section('page_title', 'Staff Attendance')
@section('page_description', 'Mark only today attendance for roaming shop staff and submit leave requests for admin review.')
@php($breadcrumbs = [['label' => 'Staff']])

@section('content')
    @php($defaultShop = $shops->first())
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-black text-slate-950">Owned Shop Staff</h1>
            <p class="text-sm font-semibold text-slate-500">Update only today&apos;s attendance and request leave for non-user staff.</p>
        </div>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
        @endif

        @if($shops->isEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                No owned shop is assigned to this account yet. Staff attendance and leave requests require a shop assignment.
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-950">Today Attendance</h2>
                        <p class="text-sm font-semibold text-slate-500">{{ $selectedDate->format('d M Y') }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    @foreach($employees as $employee)
                        @php($attendance = $attendanceRecords->get($employee->id))
                        <form method="POST" action="{{ route('shop-owner.staff.attendance.store') }}" class="grid gap-3 rounded-2xl border border-slate-100 p-4 lg:grid-cols-[1.4fr_1fr_1fr_auto]">
                            @csrf
                            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                            <input type="hidden" name="attendance_date" value="{{ $selectedDate->format('Y-m-d') }}">
                            <div>
                                <p class="font-black text-slate-900">{{ $employee->name }}</p>
                                <p class="text-xs font-semibold text-slate-500">{{ $employee->category->name }}</p>
                            </div>
                            <select name="status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                @foreach(['present' => 'Present', 'half_day' => 'Half Day', 'absent' => 'Absent', 'leave' => 'Leave'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($attendance?->status ?? 'absent') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if($shops->count() === 1 && $defaultShop)
                                <input type="hidden" name="shop_id" value="{{ $defaultShop->id }}">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700">
                                    {{ $defaultShop->name }}
                                </div>
                            @else
                                <select name="shop_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                    <option value="">Select worked shop</option>
                                    @foreach($shops as $shop)
                                        <option value="{{ $shop->id }}" @selected((int) ($attendance?->shop_id ?? old('shop_id')) === $shop->id)>{{ $shop->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <button type="submit" class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-black text-white" @disabled($shops->isEmpty())>Save</button>
                        </form>
                    @endforeach
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-black text-slate-950">Request Leave</h2>
                <form method="POST" action="{{ route('shop-owner.staff.leave-requests.store') }}" class="mt-5 space-y-3">
                    @csrf
                    <select name="employee_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="">Select employee</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                    @if($shops->count() === 1 && $defaultShop)
                        <input type="hidden" name="shop_id" value="{{ $defaultShop->id }}">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700">
                            {{ $defaultShop->name }}
                        </div>
                    @else
                        <select name="shop_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                            <option value="">Select shop</option>
                            @foreach($shops as $shop)
                                <option value="{{ $shop->id }}" @selected((int) old('shop_id') === $shop->id)>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <input type="date" name="start_date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="date" name="end_date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <textarea name="reason" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Reason"></textarea>
                    <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white" @disabled($shops->isEmpty())>Submit Leave Request</button>
                </form>

                <div class="mt-6 space-y-3">
                    @foreach($leaveRequests as $leaveRequest)
                        <div class="rounded-2xl border border-slate-100 p-4">
                            <p class="font-black text-slate-900">{{ $leaveRequest->employee->name }}</p>
                            <p class="text-xs font-semibold text-slate-500">{{ $leaveRequest->start_date->format('d M') }} to {{ $leaveRequest->end_date->format('d M') }} • {{ $leaveRequest->status }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
