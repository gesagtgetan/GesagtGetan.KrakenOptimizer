---------------------------
GesagtGetan.KrakenOptimizer
---------------------------

Image optimizer for Neos utilizing the Kraken API

| Version | Neos     |
|---------|----------|
| 1.x     | 2.3 LTS  |
| 2.x     | 3.x, 4.x |

## Installation

```
composer require gesagtgetan/krakenoptimizer
```
                            
Create a Kraken account on [https://kraken.io](https://kraken.io/?ref=a6246f4dd262) and generate new API Keys on
[https://kraken.io/account/api-credentials](https://kraken.io/account/api-credentials).

Add the credentials to your global `Settings.yaml`:

```
GesagtGetan:
  KrakenOptimizer:
    krakenOptions:
      auth:
        api_key: '<yourApiKey>'
        api_secret: '<yourApiSecret>'
```
## Usage

1. We usually want to optimize all existing resources. We can do this by running this CLI command 
```
./flow kraken:optimize
```

All existing thumbnails will be replaced by optimized version from Kraken. By default we perform ``lossy`` optimizations,
because this can dramatically decrease image size, while no noticeable decrease in image quality should occur. You can
change the optimization strategy by altering the `krakenOptions` in the settings (set `lossy: false`), along with other
arbitrary Kraken API options (see [https://kraken.io/docs/](https://kraken.io/docs/)).

#### Tip:
In case something goes wrong during the initial optimization, it's possible to resume optimization from a preferred offset.
For example `./flow kraken:optimize --offset 300` will skip the first 299 thumbnails.    

#### Warning:
> ⚠ Executing this command multiple times will send potentially already optimized images to the Kraken API and thus will still
    count towards your API quota and can lead to "over optimized" images when running lossy optimizations multiple times.

2. Now is the perfect time to activate live optimizations. All generated thumbnails after the initial optimiziation
will be send to kraken for improvement when this flag is set:
```yaml
GesagtGetan:
  KrakenOptimizer:
    liveOptimization: true
```

> ♻ Don't forget to flush your caches after adding the flag in a production environment.

3. Delete Production cache
```
FLOW_CONTEXT=Production ./flow flow:cache:flush
```

## Additional options

### optimizeOriginalResource

By enabling the `optimizeOriginalResource` flag, the original image will be replaced with an optimized version,
if no thumbnail creation was necessary for a particular image.
Neos will not create a thumbnail for an image, if the the necessary dimensions are equal or lower than the original image.

By default we do not optimize original images. Make sure you have regular backups of your server, in case something
goes wrong during optimization!

#### Usage:
```yaml
GesagtGetan:
  KrakenOptimizer:
    optimizeOriginalResource: true
```

## Troubleshooting and FAQ
**Q: I already optimized my thumbnails before installing this plugin, but I'm unhappy with the result**

A: If your thumbnails already show signs of high compression like artefacts, this plugin can not restore that missing
information. In that case it's necessary to clear all your existing thumbnails with `./flow media:clearthumbnails`.
Be aware that this can put a lot of load on your server since all thumbnails need recreation, when a page has been called.
You can activate `liveOptimization` right away, or optimize the created thumbnails later by running `./flow kraken:optimize`.

If you need all your thumbnails in all your preset sizes, you can pre-generate them by running `./flow media:createthumbnails` and
`./flow media:renderthumbnails`. Either optimize afterwads or have `liveOptimization` activated.
