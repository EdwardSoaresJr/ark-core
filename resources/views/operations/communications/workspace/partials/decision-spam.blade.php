<form
    method="POST"
    action="{{ $spamUrl }}"
    class="ops-comms-inbox__decision-form"
    onsubmit="return confirm('Mark this as spam? Later messages with the same text or link stay out of the inbox.');"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="state" value="spam">
    <button type="submit" class="ops-comms-inbox__decision-btn">Spam</button>
</form>
