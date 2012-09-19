# Agar Man

A fast-paced word game for Facebook canvas. Choose a difficulty and a time limit, then use the letters to make words - more points for rarer letters, and bonus points for longer words.

Authors: Colin Creitz

## Demo

https://apps.facebook.com/agarman

## Installing

To run the code yourself, please create a Facebook application. The file `config/php` points to several required resources that you should configure (a MySQL or compatible PDO DB, and a Memcache pool); `main.sql` contains code to initialize the DB, which you should run before accessing the app.

## Additional Resources

 * The app demonstrates serveral platform features, all tagged so they can be found easily in the code. Search for:
   * `TAG:AUTH` to see how to implement Facebook authentication
   * `TAG:REQUESTS` as an example of creating and managing requests
   * `TAG:FEED` to see how to use the Feed dialog
   * `TAG:PAYMENTS` for a sample implementation of Facebook Payments
   * `TAG:OFFERS` to learn about letting users earn your game's virtual currency
   * `TAG:ACHIEVEMENTS` and `TAG:SCORES`  for those APIs
 * The repo contains a Keynote slide deck from the Facebook World Hack based on Agar Man, WorldHackGames.key
 * You can see any errors Facebook returns by appending a "debug=1" to the url of the app.

## Contributing

All contributors must agree to and sign the [Facebook CLA](https://developers.facebook.com/opensource/cla) prior to submitting Pull Requests. We cannot accept Pull Requests until this document is signed and submitted.

## License

Copyright 2012-present Facebook, Inc.

You are hereby granted a non-exclusive, worldwide, royalty-free license to use, copy, modify, and distribute this software in source code or binary form for use in connection with the web services and APIs provided by Facebook.

As with any software that integrates with the Facebook platform, your use of this software is subject to the Facebook Developer Principles and Policies [http://developers.facebook.com/policy/]. This copyright notice shall be included in all copies or substantial portions of the software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
