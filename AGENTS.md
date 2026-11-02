<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.3. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow existing technical conventions for structure, naming, and integration. Sibling pages may be inspected for business behavior and compatibility, but must not be used as default visual or layout templates. For UI reuse, follow the Foundation Blade UI Component rules below.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Reuse the Foundation Blade UI Components in `resources/views/components/ui/` before introducing a new UI component. Reusing components does not mean copying the composition of an existing page.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

## Routing kỹ năng UI/UX và frontend

Khi một yêu cầu có thể khớp nhiều skill thiết kế, chỉ chọn bộ skill nhỏ nhất đủ giải quyết công việc. Không kích hoạt đồng thời toàn bộ nhóm skill UI chỉ vì cùng liên quan đến giao diện.

- Thiết kế giao diện mới hoặc thay đổi mạnh art direction:
  - Dùng `frontend-design` để xác lập concept, hierarchy, typography và ngôn ngữ thị giác khác biệt.
  - Dùng thêm `ui-ux-pro-max` khi cần design system, token, responsive, accessibility, dashboard, form, table, chart hoặc hướng dẫn theo stack.
- Cải thiện, làm đẹp hoặc sửa một giao diện hiện có:
  - Dùng `impeccable` làm skill chính cho visual hierarchy, spacing, layout, màu sắc, UX copy và độ hoàn thiện.
  - Chỉ bổ sung `ui-ux-pro-max` khi cần tra cứu pattern hoặc kiểm tra hệ thống UI có cấu trúc.
- Audit hoặc review UI/UX mà chưa được yêu cầu triển khai:
  - Dùng `web-design-guidelines` làm skill chính và giữ phạm vi read-only.
  - Có thể dùng `impeccable` để lập đề xuất cải thiện cụ thể, nhưng không tự sửa source code nếu người dùng chỉ yêu cầu review.
- Tinh chỉnh cảm giác sản phẩm và chi tiết design engineering:
  - Dùng `emil-design-eng` cho micro-interaction, component behavior và các chi tiết nhỏ ảnh hưởng cảm giác sử dụng.
- Motion trên web:
  - Dùng `animate` để triển khai animation mới.
  - Dùng `find-animation-opportunities` để tìm vị trí nên animate, ở chế độ read-only.
  - Dùng `improve-animations` để audit toàn bộ motion và lập kế hoạch, ở chế độ read-only.
  - Chỉ dùng `review-animations` khi người dùng gọi rõ hoặc yêu cầu review animation cụ thể.
- Mobile và nền tảng chuyên biệt:
  - Dùng `mobile-native` cho web/PWA trên điện thoại.
  - Dùng `animate-expo` cho React Native hoặc Expo.
  - Dùng `apple-design` khi yêu cầu rõ phong cách Apple, gesture, spring hoặc translucent material.
  - Dùng `write-swift` cho Swift/SwiftUI và concurrency Swift.
- Công cụ chuyên biệt:
  - Dùng `ask-sonner` khi cài đặt, sửa hoặc chẩn đoán Sonner toast.
  - Chỉ dùng `pick-ui-library` khi người dùng yêu cầu chọn thư viện.
  - Chỉ dùng `prototype` khi người dùng yêu cầu rõ nhiều phương án hoặc visual picker.
  - Dùng `animation-vocabulary` khi người dùng mô tả hiệu ứng nhưng chưa biết tên.
- Với Figma hoặc Stitch:
  - Luôn ưu tiên skill bắt buộc của Figma/Stitch cho thao tác đọc hoặc ghi trên nền tảng.
  - Các skill UI/UX ở trên chỉ hỗ trợ art direction, critique và tiêu chí chất lượng; không thay thế workflow Figma/Stitch.

Nếu cần phối hợp nhiều skill, thứ tự mặc định là: art direction -> design system hoặc implementation -> audit hoặc polish.

## Quy ước thiết kế UI độc lập

### Tái sử dụng Bộ Blade UI Component Nền Tảng

- Nguồn tái sử dụng UI là các component `x-ui.*` trong `resources/views/components/ui/`. Đọc `resources/views/admin/ui-preview.blade.php` (Bộ Blade UI Component Nền Tảng) để xem ví dụ, sau đó đọc component tương ứng để xác nhận props, slot, variant và trạng thái được hỗ trợ.
- Tái sử dụng hình thức và hành vi của component nền tảng; thiết kế cách phối hợp chúng theo tác vụ chính, actor, dữ liệu và workflow của từng màn hình. Trang preview là danh mục component, không phải mẫu bố cục cho trang nghiệp vụ.
- Không sao chép hoặc lấy bố cục của các trang nghiệp vụ hiện có làm chuẩn mặc định, kể cả cấu trúc tiêu đề, KPI, card, form, bảng hay thứ tự các vùng. Chỉ dùng một trang làm reference bố cục khi người dùng chỉ định rõ trang và khía cạnh cần áp dụng.
- Được đọc trang nghiệp vụ hiện có để hiểu route, binding Livewire, validation, quyền, dữ liệu và tương thích kỹ thuật. Việc đọc này không trao quyền kế thừa bố cục hoặc các lỗi thị giác của trang đó.
- Trước khi triển khai, trình bày tác vụ chính, bố cục đề xuất, component nền tảng sẽ dùng, nội dung luôn hiển thị và nội dung chỉ mở theo thao tác; giải thích quyết định theo nhu cầu nghiệp vụ thay vì viện dẫn một trang có sẵn.
- Dùng props, slot và variant sẵn có; không tự dựng lại control tương đương hoặc ghi đè font, màu, spacing và trạng thái của component để bắt chước trang khác. CSS bố cục thuộc phạm vi màn hình; token chung tuân theo `DESIGN.md` đã được duyệt.
- Nếu bộ nền tảng thiếu khả năng cần thiết hoặc có lỗi, nêu rõ giới hạn và đề xuất bổ sung/sửa component trước; không tự tạo phiên bản riêng trên từng trang. Thay đổi component dùng chung phải đánh giá các nơi sử dụng và tuân thủ quy định xác nhận kế hoạch.

Khi tạo hoặc sửa giao diện, agent phải bắt đầu từ brief, mục tiêu người dùng và ràng buộc nghiệp vụ của task. Không được mặc định thiết kế dựa trên Stitch, screenshot, HTML mẫu hoặc thư viện giao diện tham khảo.

Thứ tự ưu tiên nguồn thiết kế:

1. Yêu cầu trực tiếp và các quyết định đã được người dùng phê duyệt.
2. Mục tiêu sản phẩm, actor, trạng thái dữ liệu và workflow hiện tại.
3. Accessibility, responsive và khả năng sử dụng.
4. Convention kỹ thuật và component hiện có, chỉ để bảo đảm tương thích hành vi.
5. Design direction và token do agent đề xuất từ brief.
6. Reference bên ngoài hoặc `.stitch/**` chỉ khi người dùng yêu cầu rõ ràng.

Nếu task tạo hoặc sửa UI mà project chưa có `DESIGN.md` được phê duyệt, agent phải tạo `DESIGN.md` ở project root từ brief và các quyết định đã duyệt trước khi viết mã UI. Không cần đọc `collection.md`, `.stitch/AGENT_UI_GUIDE.md`, `.stitch/DESIGN.md`, `.stitch/SITE.md` hoặc `.stitch/designs/*` để làm việc đó.

Trước khi viết mã UI, agent phải trình bày design direction, component/state cần có, responsive, accessibility, file bị ảnh hưởng và ranh giới không thay đổi; chỉ triển khai sau khi người dùng xác nhận.

Không được thay đổi route, API, quyền, validation, dữ liệu hoặc workflow chỉ để làm giao diện trông giống một reference. Khi người dùng chủ động yêu cầu dùng reference, chỉ áp dụng đúng file và khía cạnh được chỉ định.

### Giao diện vận hành nội bộ Sunny Beauty

Sunny Beauty là hệ thống vận hành bằng Laravel Blade/Livewire, không phải landing page hoặc ứng dụng React/Next.js. Khi thiết kế dashboard, lịch hẹn, CRM, phòng, hóa đơn, nhân sự hoặc bảng lương:

- Ưu tiên tốc độ thao tác, khả năng quét dữ liệu, trạng thái nghiệp vụ và phòng tránh sai sót.
- Dùng đúng mẫu bố cục theo công việc: lịch/queue cho lịch hẹn, spatial board cho phòng, master-detail cho CRM, workbench cho tài chính và payroll.
- Không ép bố cục thử nghiệm, bất đối xứng cực đoan, animation cuộn, parallax hoặc hiệu ứng trang trí vào màn hình nghiệp vụ.
- Các hướng dẫn React/Next.js chỉ được dùng khi dự án thật sự sử dụng stack đó.
- Màu thương hiệu, typography và token đã được phê duyệt trong `DESIGN.md` được ưu tiên hơn các lệnh cấm màu chung của agent/skill.
- Bảng, card, sidebar và grid được phép sử dụng khi có lý do nghiệp vụ; không được lặp cùng một khuôn cho mọi module.
