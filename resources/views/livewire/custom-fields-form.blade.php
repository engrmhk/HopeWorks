<div class="space-y-4">
    @foreach ($definitions as $definition)
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ $definition->label }}
                @if ($definition->is_required)
                    <span class="text-danger-600">*</span>
                @endif
            </label>

            @switch($definition->field_type)
                @case('textarea')
                    <textarea
                        wire:model="customFields.{{ $definition->field_key }}"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                        rows="3"
                    ></textarea>
                    @break

                @case('select')
                    <select
                        wire:model="customFields.{{ $definition->field_key }}"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                    >
                        <option value="">Select...</option>
                        @foreach ($definition->options ?? [] as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    @break

                @case('number')
                    <input
                        type="number"
                        wire:model="customFields.{{ $definition->field_key }}"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                    />
                    @break

                @default
                    <input
                        type="text"
                        wire:model="customFields.{{ $definition->field_key }}"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                    />
            @endswitch

            @error('customFields.'.$definition->field_key)
                <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
            @enderror
        </div>
    @endforeach
</div>
