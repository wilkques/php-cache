# PHP Cache

[![Latest Stable Version](https://poser.pugx.org/wilkques/cache/v/stable)](https://packagist.org/packages/wilkques/cache)
[![License](https://poser.pugx.org/wilkques/cache/license)](https://packagist.org/packages/wilkques/cache)

一個輕量的檔案快取套件，`put()`/`get()`/`remember()` 這套 API 是模仿 Laravel 快取 facade 設計的（只挑了一小部分實作，不是完整移植——差異列在下方「跟 Laravel 的差異」）。

## 安裝

`composer require wilkques/cache`

## 快速開始

`Cache::make()`（跟不帶參數呼叫的 `cache()` 全域函式）永遠回傳**同一個共用實例**，透過 `Wilkques\Container\Container` 解析。

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
| `put($key, $value, $secord = null)` | 儲存一個值。`$secord` 是幾秒後過期；省略／`null` 預設 1 小時。 | `Cache::put('key', 'value', 3600);` |
| `forever($key, $value)` | 永久儲存一個值，不會過期。 | `Cache::forever('key', 'value');` |
| `get($key, $default = false)` | 取值；key 不存在或已過期回傳 `null`（`$default` 的行為請見下方註記）。 | `Cache::get('key'); // 'value' 或 null` |
| `has($key)` | `$key` 是否有未過期的值。 | `Cache::has('key'); // bool` |
| `forgot($key)` | 移除單一 key。（方法名稱就是 `forgot`，不是 `forget`——這是實際存在的方法名。） | `Cache::forgot('key');` |
| `remember($key, $expire, $callback)` | 有快取就回傳；沒有就呼叫 `$callback`、把回傳值存 `$expire` 秒後回傳。能正確區分「沒快取」跟「快取值本身是 falsy」——用 `remember()` 快取 `0`/`false`/`''`/`null`/`[]` 不會每次都重算。 | `Cache::remember('key', 3600, function () { return compute(); });` |
| `add($key, $value, $secord = null)` | key 不存在（或已過期）才儲存。**不是**防競態安全的（見下方註記）。 | `Cache::add('key', 'value', 3600); // bool` |
| `pull($key, $default = false)` | 取值同時移除該 key，一次呼叫完成。 | `Cache::pull('key');` |
| `increment($key, $value = 1)` / `decrement($key, $value = 1)` | 對數值做加／減，保留剩餘 TTL。key 不存在或已過期時視為 `0`，運算後的結果會**永久**儲存（跟 Laravel `FileStore::increment()` 行為一致）。 | `Cache::increment('visits'); // int` |
| `clear()` | 刪除整個快取目錄。 | `Cache::clear();` |
| `driver($driver = 'file')` | 依名稱解析（並快取）一個 store。目前只實作了 `'file'`。 | `Cache::driver('file');` |
| `make()` *（靜態方法）* | 透過 container 解析出共用的 `Cache` 實例。 | `Cache::make();` |

> **關於 `get()` 的 `$default` 參數**：key 不存在或已過期時，內部其實還是會產生一筆 `'data' => null` 的紀錄（不是真的「key 不存在」），而底層的陣列查找邏輯會把「存在但值是 `null`」視為「key 存在」——所以傳給 `get()`（以及建立在它之上的 `pull()`）的 `$default` **實際上永遠不會被回傳**，沒命中一律回傳 `null`。目前不要依賴 `$default` 會有任何作用。

> **關於 `add()`**：跟 Laravel 的 `FileStore::add()` 不同，這裡只是單純的「先檢查再寫入」（`has()` 接著 `put()`）——這個套件沒有檔案鎖的機制，所以兩個行程同時對同一個 key 呼叫 `add()`，有可能都判斷成「不存在」然後都寫入。單一行程使用沒問題；不要拿它當跨行程互斥鎖用。

## 設定儲存位置

檔案 store 預設用 `./storage/cache`，不會強制檔案權限。要換目錄或權限模式的話，直接對解析出來的 store 做設定：

```php
Cache::driver('file')
    ->setDirectory('/var/cache/myapp')
    ->setFilePermission(0644);
```

## 跟 Laravel 的差異

這個套件的範疇刻意比 `Illuminate\Cache` 小很多。拿真正的 Laravel `FileStore`/`Repository` 原始碼比對過，目前還缺少：

- 任何鎖機制（`lock()`、`restoreLock()`）——上面的 `add()` 只是盡力而為、非原子性的近似實作，不能當作替代品
- `DateTimeInterface`/`DateInterval` 型別的 TTL——只吃原始秒數
- file store 以外的任何 driver（沒有 Redis/Memcached/array 等）

如果你需要上面任何一項，建議改用功能完整的快取套件。

## 測試

```
composer install
vendor/bin/phpunit
```

## 授權

MIT
