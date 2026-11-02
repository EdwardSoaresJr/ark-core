<form
    method="POST"
    action="{{ $spamUrl }}"
    class="d-inline-flex"
    onsubmit="return confirm('Mark this as spam? Later messages with the same text or link stay out of the inbox.');"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="state" value="spam">
    <button type="submit" class="btn btn-sm btn-ghost-danger">Spam</button>
</form>
