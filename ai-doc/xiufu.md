/admin/profile-publish  多语言没有实现：admin.profilePublish.profileUid
admin.profilePublish.profileName
admin.profilePublish.owner
admin.profilePublish.status
admin.profilePublish.version
admin.profilePublish.published
admin.profilePublish.publishedAt
admin.profilePublish.createdAt
admin.profilePublish.action
暂无数据  {
    "message": "Call to undefined method App\\Models\\Profile::configVersions()",
    "exception": "BadMethodCallException",
    "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Support/Traits/ForwardsCalls.php",
    "line": 67,
    "trace": [
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Support/Traits/ForwardsCalls.php",
            "line": 36,
            "function": "throwBadMethodCallException",
            "class": "Illuminate\\Database\\Eloquent\\Model",
            "type": "::"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php",
            "line": 2544,
            "function": "forwardCallTo",
            "class": "Illuminate\\Database\\Eloquent\\Model",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/QueriesRelationships.php",
            "line": 1124,
            "function": "__call",
            "class": "Illuminate\\Database\\Eloquent\\Model",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Relations/Relation.php",
            "line": 119,
            "function": "Illuminate\\Database\\Eloquent\\Concerns\\{closure}",
            "class": "Illuminate\\Database\\Eloquent\\Builder",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/QueriesRelationships.php",
            "line": 1123,
            "function": "noConstraints",
            "class": "Illuminate\\Database\\Eloquent\\Relations\\Relation",
            "type": "::"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/QueriesRelationships.php",
            "line": 874,
            "function": "getRelationWithoutConstraints",
            "class": "Illuminate\\Database\\Eloquent\\Builder",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/QueriesRelationships.php",
            "line": 967,
            "function": "withAggregate",
            "class": "Illuminate\\Database\\Eloquent\\Builder",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/app/Http/Controllers/Api/V1/Admin/AdminPublishController.php",
            "line": 255,
            "function": "withCount",
            "class": "Illuminate\\Database\\Eloquent\\Builder",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php",
            "line": 46,
            "function": "profilePublishList",
            "class": "App\\Http\\Controllers\\Api\\V1\\Admin\\AdminPublishController",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Routing/Route.php",
            "line": 265,
            "function": "dispatch",
            "class": "Illuminate\\Routing\\ControllerDispatcher",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Routing/Route.php",
            "line": 211,
            "function": "runController",
            "class": "Illuminate\\Routing\\Route",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Routing/Router.php",
            "line": 822,
            "function": "run",
            "class": "Illuminate\\Routing\\Route",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php",
            "line": 180,
            "function": "Illuminate\\Routing\\{closure}",
            "class": "Illuminate\\Routing\\Router",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/app/Http/Middleware/CheckPermission.php",
            "line": 28,
            "function": "Illuminate\\Pipeline\\{closure}",
            "class": "Illuminate\\Pipeline\\Pipeline",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php",
            "line": 219,
            "function": "handle",
            "class": "App\\Http\\Middleware\\CheckPermission",
            "type": "->"
        },
        {
            "file": "/www/wwwroot/test-dns.ocerlinkdata.com/demo-dns/portal-web/app/Http/Middleware/CheckPermission.php",
            "line": 28,
            "function": "Illuminate\\Pipeline\\{closure}",
            "class": "Illuminate\\Pipeline\\Pipeline",