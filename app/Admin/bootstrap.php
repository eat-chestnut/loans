<?php

use Slowlyo\OwlAdmin\Admin;
use Slowlyo\OwlAdmin\Support\Cores\AdminPipeline;

AdminPipeline::through(
    AdminPipeline::PIPE_BASE_CRUD,
    [
        function ($schema, $next) {
            // 修改按钮样式
            $schema->columnsTogglable(false);
            return $next($schema);
        },
    ]
);
