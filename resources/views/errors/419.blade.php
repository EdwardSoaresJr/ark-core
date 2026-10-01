@php($page = \App\Ark\Runtime\Exceptions\ErrorPagePresenter::forStatus(419))

<x-errors.page
    :code="$page['code']"
    :title="$page['title']"
    :message="$page['message']"
    :primary-label="$page['primary_label']"
    :primary-url="$page['primary_url']"
    :secondary-label="$page['secondary_label']"
    :secondary-url="$page['secondary_url']"
/>
