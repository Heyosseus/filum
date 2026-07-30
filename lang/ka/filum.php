<?php

declare(strict_types=1);

return [

    'nav' => [
        'chat' => 'ჩათი',
    ],

    'page' => [
        'title' => 'ჩათი',
        'heading' => 'ჩათი',
    ],

    'sidebar' => [
        'heading' => 'კოლეგები',
        'search' => 'კოლეგების ძებნა',
        'empty' => 'სხვა მომხმარებელს ჯერ არ აქვს ანგარიში.',
        'none_found' => 'ამ ძებნას კოლეგა არ შეესაბამება.',
        'online' => 'ონლაინ',
        'offline' => 'ოფლაინ',
        'here' => 'ახლა ხაზზე',
        'away' => 'გასული',
        'last_seen' => 'ბოლოს ნანახი :time',
        'never_seen' => 'არასდროს შემოსულა',
        'invitations' => 'მოწვევები',
        'groups' => 'ჯგუფები',
        'new_group' => 'ახალი ჯგუფი',
        'group_name' => 'ჯგუფის სახელი',
        'members' => ':count წევრი',
        'accept' => 'მიღება',
        'decline' => 'უარი',
        'invite' => 'მოწვევა',
        'leave_group' => 'ჯგუფიდან გასვლა',
        'delete_group' => 'ჯგუფის წაშლა',
        'invited_by' => ':name-ისგან',
        'group_needs_name' => 'მიუთითეთ ჯგუფის სახელი.',
        'not_yours_to_delete' => 'ჯგუფის წაშლა მხოლოდ მფლობელს შეუძლია.',
    ],

    'conversation' => [
        'none_selected' => 'აირჩიეთ კოლეგა საუბრის დასაწყებად.',
        'empty' => 'ჯერ შეტყობინებები არ არის. დაიწყეთ საუბარი.',
        'load_older' => 'ძველი შეტყობინებების ჩატვირთვა',
        'you' => 'თქვენ',
        'sent_at' => 'გაგზავნილია :time',
        'today' => 'დღეს',
        'yesterday' => 'გუშინ',
    ],

    'composer' => [
        'placeholder' => 'დაწერეთ შეტყობინება…',
        'send' => 'გაგზავნა',
        'too_long' => 'შეტყობინება :max სიმბოლოზე გრძელია.',
        'rate_limited' => 'ძალიან ბევრი შეტყობინება. სცადეთ :seconds წამში.',
        'empty' => 'ჯერ დაწერეთ რაიმე.',
    ],

    'notification' => [
        'open' => 'ჩათის გახსნა',
        'invited_body' => 'მოგიწვიათ ჯგუფში :group',
    ],

    'overlay' => [
        'open' => 'ჩათის გახსნა',
        'close' => 'ჩათის დახურვა',
        'unread' => ':count წაუკითხავი',
    ],

    'errors' => [
        'not_participant' => 'თქვენ არ ხართ ამ საუბრის მონაწილე.',
        'missing_tables' => 'Filum-ის ცხრილები ვერ მოიძებნა. გაუშვით: php artisan vendor:publish --tag=filum-migrations && php artisan migrate',
    ],

    'install' => [
        'published' => 'Filum-ის კონფიგურაცია და მიგრაციები გამოქვეყნდა.',
        'next' => 'შემდეგ გაუშვით: php artisan migrate',
        'plugin' => 'შემდეგ დაამატეთ FilumPlugin::make() თქვენს პანელის პროვაიდერში.',
        'done' => 'Filum მზადაა.',
    ],

];
