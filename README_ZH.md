# PHP Cache

[![Latest Stable Version](https://poser.pugx.org/wilkques/cache/v/stable)](https://packagist.org/packages/wilkques/cache)
[![License](https://poser.pugx.org/wilkques/cache/license)](https://packagist.org/packages/wilkques/cache)

一個輕量的檔案快取套件，`put()`/`get()`/`remember()` 這套 API 是模仿 Laravel 快取 facade 設計的（只挑了一小部分實作，不是完整移植——差異列在下方「跟 Laravel 的差異」）。

## 安裝

`composer require wilkques/cache`

## 快速開始

`Cache::make()`（跟不帶參數呼叫的 `cache()` 全域函式）在**整個 PHP 行程的生命週期內**永遠回傳**同一個共用實例**，透過 `Wilkques\Container\Container` 解析。在常駐的 worker（PHP-FPM、長駐的 CLI daemon）底下，這個「行程」指的是整個 worker 的生命週期，不是單一個請求——這點主要跟下面的 `array` driver 有關，因為檔案 store 的實際資料是存在磁碟上的，跟哪一個 `Cache`/`File` 物件實例去操作它無關。

```php
use Wilkques\Cache\Cache;

// 儲存一個值，預設 1 小時後過期，也可以自訂秒數
Cache::put('key', 'value');
Cache::put('key', 'value', 3600);

// 永久儲存
Cache::forever('key', 'value');

// 取值（不存在或已過期回傳 null）
Cache::get('key');

// 檢查是否有未過期的值
Cache::has('key');

// 有快取就回傳，沒有就執行 callback、把結果存起來再回傳
Cache::remember('key', 3600, function () {
    return expensive_computation();
});

// 移除單一 key
Cache::forgot('key');

// 清空整個快取目錄
Cache::clear();

// key 不存在（或已過期）才儲存
Cache::add('key', 'value', 3600);

// 取值同時移除 key，一次呼叫完成
Cache::pull('key');

// 遞增／遞減數值，保留剩餘 TTL
Cache::increment('visits');
Cache::increment('visits', 5);
Cache::decrement('visits');

// TTL 也可以是 DateInterval，或 DateTime/DateTimeImmutable 這種時間點物件
Cache::put('key', 'value', new DateInterval('PT1H'));
Cache::put('key', 'value', new DateTime('+1 hour'));

// 跨行程／跨請求協調某個操作
Cache::driver('file')->lock('import-job', 10)->get(function () {
    // 同一時間只會有一個行程／請求執行這裡
});

// 行程內的記憶體快取——見下方「Driver」
Cache::driver('array')->put('key', 'value', 60);
```

`cache()` 全域函式是同樣功能的捷徑：

```php
cache('key');             // 等同 Cache::get('key')
cache('key', 'default');  // 等同 Cache::get('key', 'default')
cache();                  // 等同 Cache::make()，拿到 Cache 實例本身
```

## API

| 方法 | 說明 | 範例 |
| --- | --- | --- |
| `put($key, $value, $secord = null)` | 儲存一個值。`$secord` 是幾秒後過期——可以是 `int`、`null`（預設 1 小時）、`DateInterval`，或 `DateTime`/`DateTimeImmutable`／任何有 `getTimestamp()` 方法的物件。 | `Cache::put('key', 'value', 3600);` |
| `forever($key, $value)` | 永久儲存一個值，不會過期。 | `Cache::forever('key', 'value');` |
| `get($key, $default = false)` | 取值；key 不存在或已過期回傳 `null`（`$default` 的行為請見下方註記）。 | `Cache::get('key'); // 'value' 或 null` |
| `has($key)` | `$key` 是否有未過期的值。 | `Cache::has('key'); // bool` |
| `forgot($key)` | 移除單一 key。（方法名稱就是 `forgot`，不是 `forget`——這是實際存在的方法名。） | `Cache::forgot('key');` |
| `remember($key, $expire, $callback)` | 有快取就回傳；沒有就呼叫 `$callback`、把回傳值存 `$expire` 秒後回傳。能正確區分「沒快取」跟「快取值本身是 falsy」——用 `remember()` 快取 `0`/`false`/`''`/`null`/`[]` 不會每次都重算。 | `Cache::remember('key', 3600, function () { return compute(); });` |
| `add($key, $value, $secord = null)` | key 不存在（或已過期）才儲存。單獨使用**不是**防競態安全的（見下方註記）——需要的話用 `lock()` 包起來。 | `Cache::add('key', 'value', 3600); // bool` |
| `pull($key, $default = false)` | 取值同時移除該 key，一次呼叫完成。 | `Cache::pull('key');` |
| `increment($key, $value = 1)` / `decrement($key, $value = 1)` | 對數值做加／減，保留剩餘 TTL。key 不存在或已過期時視為 `0`，運算後的結果會**永久**儲存（跟 Laravel `FileStore::increment()` 行為一致）。 | `Cache::increment('visits'); // int` |
| `lock($name, $seconds = 0, $owner = null)` | 取得一個 `Lock`，用來跨行程／跨請求協調對 `$name` 的存取（只有 file store 支援，見下方「鎖機制」）。 | `Cache::driver('file')->lock('job', 10);` |
| `restoreLock($name, $owner)` | 用先前產生的 owner token 取得同一把 `$name` 的 `Lock`，讓不同呼叫端可以釋放同一把鎖。 | `Cache::driver('file')->restoreLock('job', $owner);` |
| `clear()` | 刪除整個快取目錄（array driver 的話則是清空記憶體內的資料）。 | `Cache::clear();` |
| `driver($driver = 'file')` | 依名稱解析（並快取）一個 store——`'file'` 或 `'array'`（見下方「Driver」）。 | `Cache::driver('file');` |
| `make()` *（靜態方法）* | 透過 container 解析出共用的 `Cache` 實例。 | `Cache::make();` |

> **關於 `get()` 的 `$default` 參數**：key 不存在或已過期時，內部其實還是會產生一筆 `'data' => null` 的紀錄（不是真的「key 不存在」），而底層的陣列查找邏輯會把「存在但值是 `null`」視為「key 存在」——所以傳給 `get()`（以及建立在它之上的 `pull()`）的 `$default` **實際上永遠不會被回傳**，沒命中一律回傳 `null`。目前不要依賴 `$default` 會有任何作用。

> **關於 `add()`**：跟 Laravel 的 `FileStore::add()` 不同，它本身只是單純的「先檢查再寫入」（`has()` 接著 `put()`）——這兩步之間如果有其他行程同時寫入，並不是防競態安全的。如果你需要真正的跨行程互斥，改用 `lock()` 把整個操作包起來（見下方「鎖機制」），不要單靠 `add()`。

## 鎖機制

file store 的 `lock()` 會回傳一個用真正的 `flock()` 檔案鎖實作的 `Lock`（一個獨立的小型元件——`Wilkques\Filesystem\Filesystem` 本身沒有鎖的 API，所以這裡不透過它）：

```php
$lock = Cache::driver('file')->lock('import-job', 10); // 最多持有 10 秒

// 持有鎖的期間執行一個 callback；結束後一定會釋放
//（就算 callback 丟出例外也一樣），並回傳 callback 的結果
$result = $lock->get(function () {
    return do_the_import();
});

// 或是手動取得／釋放——成功跟失敗兩條路徑都要釋放，
// 因為這個套件的 PHP 5.3 底線沒有 try/finally
if ($lock->acquire()) {
    try {
        do_the_import();
    } catch (\Exception $e) {
        $lock->release();

        throw $e;
    }

    $lock->release();
}
```

取鎖失敗（已經被別的行程持有）時，`acquire()`/`get()` 會回傳 `false`，不會阻塞等待也不會丟例外——沒有像 Laravel `Lock::block()` 那種帶 timeout 的輪詢等待機制。要讓不同的呼叫端釋放別處取得的鎖，把它的 owner token 傳給 `restoreLock()`：

```php
$owner = $lock->getOwner();
// ... 之後，可能在另一個請求裡 ...
Cache::driver('file')->restoreLock('import-job', $owner)->release();
```

## Driver

| Driver | `Cache::driver('...')` | 說明 |
| --- | --- | --- |
| File（預設） | `'file'` | 資料存在磁碟上，位置可設定；跨請求／跨行程共用。 |
| Array | `'array'` | 只存在行程內的記憶體，完全不碰磁碟，行程結束就消失。**跟「每個請求獨立」不一樣**：在常駐 worker（PHP-FPM、長駐的 CLI daemon）底下，行程的生命週期比單一請求長，所以某次請求寫入的資料，同一個 worker 處理的下一次請求還是看得到，直到行程本身重啟為止。適合測試，或短命的 CLI 腳本；如果你需要 FPM 底下真正的「每請求隔離」，這個不是答案。支援跟 file store 一樣的 `put`/`get`/`has`/`forgot`/`forever`/`add`/`pull`/`increment`/`decrement`/`remember`/`clear` API，但沒有 `lock()`/`restoreLock()`。 |

Redis/Memcached 等 driver 沒有實作——見下方「跟 Laravel 的差異」。

## 設定儲存位置

檔案 store 預設用 `./storage/cache`，不會強制檔案權限。要換目錄或權限模式的話，直接對解析出來的 store 做設定：

```php
Cache::driver('file')
    ->setDirectory('/var/cache/myapp')
    ->setFilePermission(0644);
```

## 跟 Laravel 的差異

這個套件的範疇刻意比 `Illuminate\Cache` 小很多。拿真正的 Laravel `FileStore`/`Repository` 原始碼比對過，目前還缺少：

- Redis/Memcached/DynamoDB 等 driver——目前只有 `file` 跟記憶體內的 `array`（見上方「Driver」）。刻意不做：這個套件沒辦法驗證一個連不上、跑不了測試的 driver。
- `lock()` 帶 timeout 輪詢等待的版本（Laravel 的 `Lock::block()`）——這裡的 `acquire()`/`get()` 只會嘗試一次，鎖被佔用就立刻回傳 `false`，沒有輪詢等待機制。
- 快取標籤（`Cache::tags(...)`）跟快取事件。

如果你需要上面任何一項，建議改用功能完整的快取套件。

## 測試

```
composer install
vendor/bin/phpunit
```

## 授權

MIT
