@if($article->faqList() !== [])
<section class="blog-faq mt-5 pt-4 border-top" id="faq">
    <h2 class="h4 fw-bold mb-4">Frequently asked questions</h2>
    <div class="accordion" id="articleFaq">
        @foreach($article->faqList() as $index => $faq)
            <div class="accordion-item">
                <h3 class="accordion-header" id="faq-heading-{{ $index }}">
                    <button class="accordion-button {{ $index > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse-{{ $index }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="faq-collapse-{{ $index }}">
                        {{ $faq['question'] }}
                    </button>
                </h3>
                <div id="faq-collapse-{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" aria-labelledby="faq-heading-{{ $index }}" data-bs-parent="#articleFaq">
                    <div class="accordion-body">
                        {!! nl2br(e($faq['answer'])) !!}
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif
