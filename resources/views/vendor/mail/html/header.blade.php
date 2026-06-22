@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/logo-email.png'))) }}" class="logo" alt="CheckMate Logo">
</a>
</td>
</tr>
