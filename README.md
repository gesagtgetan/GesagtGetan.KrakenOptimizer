---------------------------
GesagtGetan.KrakenOptimizer
---------------------------

**!WARNING! We are currently field testing this package on a live site, do not use this plugin in your project until further notice**

Image optimizer for Neos utilizing the Kraken API

| Version | Neos     |
|---------|----------|
| 1.*     | 2.3 LTS  |
| 2.*     | 3.*      |

## Installation

```
composer require gesagtgetan/krakenoptimizer
```
                            
Create a Kraken account on [https://kraken.io](https://kraken.io) and generate new API Keys on
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

#### Warning:
Executing this command multiple times will send potentially already optimized images to the Kraken API and thus will still
count towards your API quota and can lead to "over optimized" images when running lossy optimizations multiple times. 


2. Now is the perfect time to activate live optimizations. All generated thumbnails after the initial optimiziation
will be send to kraken for improvement when this flag is set:
 ```
 GesagtGetan:
   KrakenOptimizer:
     krakenOptions:
        liveOptimization: true
 ```
