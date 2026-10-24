@props(['align' => 'left'])
<td {{ $attributes->class(['px-4 py-3 align-middle text-fg first:pl-5 last:pr-5 [.table-dense_&]:py-2', 'text-right tabular' => $align === 'right']) }}>{{ $slot }}</td>
