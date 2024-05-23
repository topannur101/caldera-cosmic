<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute 필드는 반드시 수락되어야 합니다.',
    'accepted_if' => ':other가 :value일 때 :attribute 필드는 반드시 수락되어야 합니다.',
    'active_url' => ':attribute 필드는 유효한 URL이어야 합니다.',
    'after' => ':attribute 필드는 반드시 :date 이후의 날짜여야 합니다.',
    'after_or_equal' => ':attribute 필드는 반드시 :date 이후이거나 같은 날짜여야 합니다.',
    'alpha' => ':attribute 필드는 오직 문자만 포함할 수 있습니다.',
    'alpha_dash' => ':attribute 필드는 문자, 숫자, 대시 및 밑줄만 포함할 수 있습니다.',
    'alpha_num' => ':attribute 필드는 오직 문자와 숫자만 포함할 수 있습니다.',
    'array' => ':attribute 필드는 배열이어야 합니다.',
    'ascii' => ':attribute 필드는 단일 바이트 영숫자 및 기호만 포함해야 합니다.',
    'before' => ':attribute 필드는 반드시 :date 이전의 날짜여야 합니다.',
    'before_or_equal' => ':attribute 필드는 반드시 :date 이전이거나 같은 날짜여야 합니다.',
    'between' => [
        'array' => ':attribute 필드는 반드시 :min에서 :max 항목 사이여야 합니다.',
        'file' => ':attribute 필드는 반드시 :min에서 :max 킬로바이트 사이여야 합니다.',
        'numeric' => ':attribute 필드는 반드시 :min에서 :max 사이여야 합니다.',
        'string' => ':attribute 필드는 반드시 :min에서 :max 문자 사이여야 합니다.',
    ],
    'boolean' => ':attribute 필드는 반드시 true 또는 false여야 합니다.',
    'can' => ':attribute 필드에는 허용되지 않은 값이 포함되어 있습니다.',
    'confirmed' => ':attribute 확인이 일치하지 않습니다.',
    'current_password' => '비밀번호가 잘못되었습니다.',
    'date' => ':attribute 필드는 유효한 날짜여야 합니다.',
    'date_equals' => ':attribute 필드는 반드시 :date와 같은 날짜여야 합니다.',
    'date_format' => ':attribute 필드는 반드시 :format 형식과 일치해야 합니다.',
    'decimal' => ':attribute 필드는 반드시 :decimal 소수 자릿수를 가져야 합니다.',
    'declined' => ':attribute 필드는 거부되어야 합니다.',
    'declined_if' => ':other가 :value일 때 :attribute 필드는 거부되어야 합니다.',
    'different' => ':attribute 필드와 :other는 반드시 달라야 합니다.',
    'digits' => ':attribute 필드는 반드시 :digits 자리 숫자여야 합니다.',
    'digits_between' => ':attribute 필드는 반드시 :min에서 :max 자리 숫자 사이여야 합니다.',
    'dimensions' => ':attribute 필드는 유효하지 않은 이미지 크기를 가지고 있습니다.',
    'distinct' => ':attribute 필드에 중복 값이 있습니다.',
    'doesnt_end_with' => ':attribute 필드는 다음 중 하나로 끝나면 안 됩니다: :values.',
    'doesnt_start_with' => ':attribute 필드는 다음 중 하나로 시작하면 안 됩니다: :values.',
    'email' => ':attribute 필드는 반드시 유효한 이메일 주소여야 합니다.',
    'ends_with' => ':attribute 필드는 다음 중 하나로 끝나야 합니다: :values.',
    'enum' => '선택된 :attribute가 유효하지 않습니다.',
    'exists' => '선택된 :attribute가 유효하지 않습니다.',
    'extensions' => ':attribute 필드는 다음 확장자 중 하나를 가져야 합니다: :values.',
    'file' => ':attribute 필드는 파일이어야 합니다.',
    'filled' => ':attribute 필드는 반드시 값이 있어야 합니다.',
    'gt' => [
        'array' => ':attribute 필드는 반드시 :value 항목보다 많아야 합니다.',
        'file' => ':attribute 필드는 반드시 :value 킬로바이트보다 커야 합니다.',
        'numeric' => ':attribute 필드는 반드시 :value보다 커야 합니다.',
        'string' => ':attribute 필드는 반드시 :value 문자보다 많아야 합니다.',
    ],
    'gte' => [
        'array' => ':attribute 필드는 반드시 :value 항목 이상이어야 합니다.',
        'file' => ':attribute 필드는 반드시 :value 킬로바이트 이상이어야 합니다.',
        'numeric' => ':attribute 필드는 반드시 :value 이상이어야 합니다.',
        'string' => ':attribute 필드는 반드시 :value 문자 이상이어야 합니다.',
    ],
    'hex_color' => ':attribute 필드는 유효한 16진수 색상이어야 합니다.',
    'image' => ':attribute 필드는 이미지여야 합니다.',
    'in' => '선택된 :attribute가 유효하지 않습니다.',
    'in_array' => ':attribute 필드는 반드시 :other에 있어야 합니다.',
    'integer' => ':attribute 필드는 정수여야 합니다.',
    'ip' => ':attribute 필드는 유효한 IP 주소여야 합니다.',
    'ipv4' => ':attribute 필드는 유효한 IPv4 주소여야 합니다.',
    'ipv6' => ':attribute 필드는 유효한 IPv6 주소여야 합니다.',
    'json' => ':attribute 필드는 유효한 JSON 문자열이어야 합니다.',
    'list' => ':attribute 필드는 목록이어야 합니다.',
    'lowercase' => ':attribute 필드는 소문자여야 합니다.',
    'lt' => [
        'array' => ':attribute 필드는 반드시 :value 항목보다 적어야 합니다.',
        'file' => ':attribute 필드는 반드시 :value 킬로바이트보다 작아야 합니다.',
        'numeric' => ':attribute 필드는 반드시 :value보다 작아야 합니다.',
        'string' => ':attribute 필드는 반드시 :value 문자보다 적어야 합니다.',
    ],
    'lte' => [
        'array' => ':attribute 필드는 반드시 :value 항목보다 많아서는 안 됩니다.',
        'file' => ':attribute 필드는 반드시 :value 킬로바이트 이하이어야 합니다.',
        'numeric' => ':attribute 필드는 반드시 :value 이하이어야 합니다.',
        'string' => ':attribute 필드는 반드시 :value 문자 이하이어야 합니다.',
    ],
    'mac_address' => ':attribute 필드는 유효한 MAC 주소여야 합니다.',
    'max' => [
        'array' => ':attribute 필드는 :max 항목을 초과할 수 없습니다.',
        'file' => ':attribute 필드는 :max 킬로바이트를 초과할 수 없습니다.',
        'numeric' => ':attribute 필드는 :max를 초과할 수 없습니다.',
        'string' => ':attribute 필드는 :max 문자를 초과할 수 없습니다.',
    ],
    'max_digits' => ':attribute 필드는 :max 자릿수를 초과할 수 없습니다.',
    'mimes' => ':attribute 필드는 다음 타입 중 하나의 파일이어야 합니다: :values.',
    'mimetypes' => ':attribute 필드는 다음 타입 중 하나의 파일이어야 합니다: :values.',
    'min' => [
        'array' => ':attribute 필드는 최소한 :min 항목을 가져야 합니다.',
        'file' => ':attribute 필드는 최소한 :min 킬로바이트여야 합니다.',
        'numeric' => ':attribute 필드는 최소한 :min이어야 합니다.',
        'string' => ':attribute 필드는 최소한 :min 문자여야 합니다.',
    ],
    'min_digits' => ':attribute 필드는 최소한 :min 자릿수를 가져야 합니다.',
    'missing' => ':attribute 필드는 없어야 합니다.',
    'missing_if' => ':other가 :value일 때 :attribute 필드는 없어야 합니다.',
    'missing_unless' => ':other가 :value가 아닌 한 :attribute 필드는 없어야 합니다.',
    'missing_with' => ':values가 있을 때 :attribute 필드는 없어야 합니다.',
    'missing_with_all' => ':values가 있을 때 :attribute 필드는 없어야 합니다.',
    'multiple_of' => ':attribute 필드는 :value의 배수여야 합니다.',
    'not_in' => '선택된 :attribute가 유효하지 않습니다.',
    'not_regex' => ':attribute 필드 형식이 유효하지 않습니다.',
    'numeric' => ':attribute 필드는 숫자여야 합니다.',
    'password' => [
        'letters' => ':attribute 필드는 최소한 한 개의 문자를 포함해야 합니다.',
        'mixed' => ':attribute 필드는 최소한 한 개의 대문자와 한 개의 소문자를 포함해야 합니다.',
        'numbers' => ':attribute 필드는 최소한 한 개의 숫자를 포함해야 합니다.',
        'symbols' => ':attribute 필드는 최소한 한 개의 기호를 포함해야 합니다.',
        'uncompromised' => '제공된 :attribute가 데이터 유출에서 발견되었습니다. 다른 :attribute를 선택해 주세요.',
    ],
    'present' => ':attribute 필드는 있어야 합니다.',
    'present_if' => ':other가 :value일 때 :attribute 필드는 있어야 합니다.',
    'present_unless' => ':other가 :value가 아닌 한 :attribute 필드는 있어야 합니다.',
    'present_with' => ':values가 있을 때 :attribute 필드는 있어야 합니다.',
    'present_with_all' => ':values가 있을 때 :attribute 필드는 있어야 합니다.',
    'prohibited' => ':attribute 필드는 금지되어 있습니다.',
    'prohibited_if' => ':other가 :value일 때 :attribute 필드는 금지되어 있습니다.',
    'prohibited_unless' => ':other가 :values에 없는 한 :attribute 필드는 금지되어 있습니다.',
    'prohibits' => ':attribute 필드는 :other가 존재하지 않도록 합니다.',
    'regex' => ':attribute 필드 형식이 유효하지 않습니다.',
    'required' => ':attribute 필드는 필수입니다.',
    'required_array_keys' => ':attribute 필드는 다음 항목을 포함해야 합니다: :values.',
    'required_if' => ':other가 :value일 때 :attribute 필드는 필수입니다.',
    'required_if_accepted' => ':other가 수락된 경우 :attribute 필드는 필수입니다.',
    'required_if_declined' => ':other가 거부된 경우 :attribute 필드는 필수입니다.',
    'required_unless' => ':other가 :values에 없는 한 :attribute 필드는 필수입니다.',
    'required_with' => ':values가 있을 때 :attribute 필드는 필수입니다.',
    'required_with_all' => ':values가 있을 때 :attribute 필드는 필수입니다.',
    'required_without' => ':values가 없을 때 :attribute 필드는 필수입니다.',
    'required_without_all' => ':values 중 하나도 없을 때 :attribute 필드는 필수입니다.',
    'same' => ':attribute 필드는 :other와 일치해야 합니다.',
    'size' => [
        'array' => ':attribute 필드는 반드시 :size 항목을 포함해야 합니다.',
        'file' => ':attribute 필드는 반드시 :size 킬로바이트여야 합니다.',
        'numeric' => ':attribute 필드는 반드시 :size여야 합니다.',
        'string' => ':attribute 필드는 반드시 :size 문자여야 합니다.',
    ],
    'starts_with' => ':attribute 필드는 다음 중 하나로 시작해야 합니다: :values.',
    'string' => ':attribute 필드는 문자열이어야 합니다.',
    'timezone' => ':attribute 필드는 유효한 시간대여야 합니다.',
    'unique' => ':attribute는 이미 사용 중입니다.',
    'uploaded' => ':attribute 업로드에 실패했습니다.',
    'uppercase' => ':attribute 필드는 대문자여야 합니다.',
    'url' => ':attribute 필드는 유효한 URL이어야 합니다.',
    'ulid' => ':attribute 필드는 유효한 ULID여야 합니다.',
    'uuid' => ':attribute 필드는 유효한 UUID여야 합니다.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
