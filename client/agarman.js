/**
* Copyright 2012 Facebook, Inc.
*
* Licensed under the Apache License, Version 2.0 (the "License"); you may
* not use this file except in compliance with the License. You may obtain
* a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
* WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
* License for the specific language governing permissions and limitations
* under the License.
*/

// Controls for the demo
// Each of these flags - they get their values from index.php - is used to gate
// the associated functionality. If auth is true, we will do FB auth, and so on.
$(function() {
auth = settings.auth; requests = settings.requests;
feed = settings.feed; scores = settings.scores;
achievements = settings.achievements; payments = settings.payments;
offers = settings.offers;
});

// Game logic, from here to the comment containing "END OF GAME LOGIC"
function buttonDispatch(id) {
    var mapping = {
        initmatch: initMatch.bind(undefined, $('#difficulty').slider('value'),
                                  $('#length').slider('value')),
        quitmatch: quitMatch
    };
    var result = mapping[id].call();
    console.log("Clicked ", id);
    console.log("Got ", result);
}

function typeIn(letter) {
    typeIn.ax = typeIn.ax ? typeIn.ax + letter : letter;
    typeIn.update();
}

typeIn.enabled = false;
typeIn.clear = function() {typeIn.ax = ''; typeIn.update();};
typeIn.submit = function() {
    if(checkWord.bind(Match.current, typeIn.ax, true).call()) {
        var list = $('#wordsplayed');
        list.children().remove();
        for (word in Match.current.words) {
            var score = Match.current.words[word];
            var row = $('<tr />');
            var dt = $('<td />', {class: 'playedword', text: word});
            var dd = $('<td />', {class: 'playedscore', text: score});
            row.append(dt);
            row.append(dd);
            list.append(row);
        }
    }
    typeIn.clear();
};

typeIn.update = function() {
//    $('#answer').text('');
    $('#answer').html('&nbsp' + typeIn.ax);
    wordOK(checkWord.bind(Match.current, typeIn.ax, false).call());
//    $('#answer').text(typeIn.ax);
};
typeIn.enable = function(state) {
    typeIn.enabled = state;
    $('.letter').button('option', 'disabled', !state);
};

function wordOK(yes) {
    var txt, state;
    if(yes) {
        txt = 'submit & clear';
        state = false;
    } else {
        txt = 'submit & clear';
        state = true;
    }
    var wordok = $('#wordok');
    var oktext = $('#oktext');
    oktext.text(txt);
    wordok.toggleClass('graybutton', state);
    if(!wordok.hasClass('initialized')) {
        wordok.toggleClass('initialized', true).
            toggleClass('ui-widget-default', true).
            toggleClass('ui-corner-all', true).
            toggleClass('ui-state-default', true).
            css('font-weight', (state? 'normal' : 'bold')).
            click(typeIn.submit);
    }
}

function initMatch(difficulty, length) {
    if(Match && Match.current && Match.current.interval) {
        window.clearInterval(Match.current.interval);
    }
    $.get('?f=i&d='+difficulty+'&ln='+length+'&'+settings.sr, function(x) {
        $('#scores').children().remove();
        $('#scores').text('');
        $('#timer').text('');
        $('#wordsplayed').children().remove();
        x = $.parseJSON(x);
        console.log(x);
        Match.current = new Match(x);
        var lettersElement = $('#letters');
        $('.letter').remove();
        Match.current.letterSet.forEach(function(letter) {
            lettersElement.append($('<div />', {class: 'letter',
                                                id: letter,
                                                text: letter}));
        });
        $('.letter').each(function(index,element) {
            $(element).button()
                .click(typeIn.bind(undefined, element.id));
            $(element).button('option', 'disabled', true);
        });
        startMatch();
    });
}

function quitMatch() {
    if (Match.interval) window.clearInterval(Match.interval);
    $('.letter').remove();
    $('#wordok').toggleClass('ui-state-disabled', true);
    displayMode('setup');
}

function startMatch() {
    if(startMatch.lock) return false;
    startMatch.lock = true;
    $('#initmatch').button('disabled', true);
    $.get('?f=h&m=' + Match.current.matchID+'&'+settings.sr, function(x) {
        Match.current.hashes = $.parseJSON(x);
        typeIn.enable(true);
        Match.current.clock(true);
        displayMode('game');
    });
}

function displayMode(mode) {
    var transitionTime = 250;
    var toHide = '#setupcontainer', toShow = '#gamecontainer', hideDirection = 'up', showDirection = 'down';
    if(mode != 'game') {
        toHide = '#gamecontainer'; toShow = '#setupcontainer'; hideDirection = 'down'; showDirection = 'up';
        startMatch.lock = false;
    } else {
        $('#wordok').toggleClass('ui-state-disabled', false);
    }

    $(toHide).hide(); $(toShow).show();
/*
    $(toHide).hide('slide', {'direction': hideDirection}, transitionTime,
                   function () {
                       $(toShow).show('slide', {'direction': showDirection}, function)
                   }
                  );
*/
}

function Match(properties) {
    this.matchID = properties.matchID;
    this.hashes = [];
    this.words = {};
    this.scores = {};
    this.timeLimit = properties.timeLimit;
    this.letterSet = properties.letterSet;
    this.rules = properties.rules;
    this.details = properties.details;
    this.timer = $('#timer');
    Match.finishUp = false;
    this.ok = $('#wordok');
    this.ok.progress = function (pct) {
        this.css('background-size', (100*pct).toString() + '%, 1px');
        var intensity = Math.round(pct * 255).toString(16);
        this.css('background-color', '#FF' + intensity + intensity);
    }
    this.ok.button('enable');
    this.ok.css({
        'background-color': 'red',
        'background-repeat': 'no-repeat, no-repeat',
        'background-position': 'left, bottom'
    });
    this.clock = function (init) {
        if(init) {
            this.end = this.timeLimit*1000
                + new Date().getTime();
            this.interval = window.setInterval(this.clock.bind(this), 100);
        }
        var now = new Date().getTime();
        var remaining = Math.max(Math.floor((this.end - now)/1000), 0);

        this.timer.text(remaining);
        this.ok.progress(remaining / this.timeLimit);
        $('#timer').text(Math.max(Math.floor((this.end - now)/1000), 0));
        if(!Match.hold && this.end <= now || Match.finishUp) { // clean up
            typeIn.enable(false);
            window.clearInterval(this.interval);
            this.timer.text("");
            $('#answer').text('');
            $('#wordok').toggleClass('ui-state-disabled, true');
            this.submitWords();
        }
    };
    this.submitWords = function() {
        typeIn.enable(false);
        // $('.letter').remove();
        $.post('?f=s', {m: this.matchID, w: Object.keys(this.words)}, (function(x) {
            x= $.parseJSON(x);
            console.log("submitted words, got: ", x);
            var myScore = x[settings['uid']];
            var row = $('<tr />');
            var dt = $('<td />', {class: 'playedword total', text: 'Total'});
            var dd = $('<td />', {class: 'playedscore total', text: myScore});
            row.append(dt);
            row.append(dd);
            $('#wordsplayed').append(row);
            var scoreboard = $('#scores');
            scoreboard.append('<p />').text('Your score: ');
            for(player in x) { // only one player for now
                var pic = getPicture(player);
                var name = getName(player);

                scoreboard.append(
                    $('<div />', {id: player, class: 'score'}).append(
                        $('<div />', {class: 'playerpic'}).append(
                            $('<img />', {src: pic})
                        )
                    ).append(
                        $('<div />', {class: 'playername', text: name})
                    ).append(
                        $('<div />', {class: 'playerscore', text: x[player]})
                    ));
            }
            //TAG:SCORES
            if(scores) {
                scoreboard.append(
                    $('<div />',
                      { id: 'highscorebutton', text: "High Scores", }
                     ).button().click(popLeaders)
                );
            }
            ///TAG:SCORES
            //TAG:FEED
            // If the user played a cool word, let them brag about it
            // by claiming it as their super identity
            if(feed) {
                var wordsByScore = Object.keys(this.words).sort(
                    (function(a, b) { return this.words[b] - this.words[a]; }).
                        bind(this)
                );
                if(isCool(wordsByScore[0])) {
                    brag('word', {
                        WORD: wordsByScore[0][0].toUpperCase() +
                            wordsByScore[0].slice(1),
                        POINTS: this.words[wordsByScore[0]],
                        LETTERS: this.letterSet
                    });
                }
            }


            ///TAG:FEED
        }).bind(this));
    };
}

function isCool(word) {
    if(typeof(word) === 'undefined') return false;
    if(
        word.length >= 7
        // || some other criteria you like, maybe a rarity check?
    ) return true;
    else return false;
}

function checkWord(word, submit) {
    if(!this.words[word] &&
       this.hashes.indexOf(hex_md5(word + this.matchID)) > -1) {
        var scorelen = word.length+1;
        var multiplier = 1;
        while(! (multiplier = this.rules.lengths[--scorelen]));

        var score = 0;
        for(var i = 0; i < word.length; ++i) score += this.rules.letters[word[i]];
        if(submit) this.words[word] = score*multiplier;
        return score;
    }
    return false;
}

Match.current = undefined;

var sliders = {
    difficulty: [
        'easy',
        'challenging',
        'pretty hard',
    ],
    length: [
        '15 seconds',
        '60 seconds',
        '99 seconds',
    ]
};

function labelSlider(element, value) {
    $(element).next('.sliderlabel').text(sliders[element.id][value]);
}

function ctr(element) {
    $(element).position({my: 'center', at: 'center center', of: $(element).parent()});
}

function init() {
    var uid = settings['uid']; // for the homebrew auth
    var name = (settings.profile && settings.profile.first_name) || uid;
    wordOK(false);
    $('#id').text('Hello, ' + name);
    $('.jqbutton').each(function(index, element) {
        $(element).button().click(buttonDispatch.bind(undefined, element.id));
    });
    ctr($('#initmatch'));

    $('.jqslider').each(function(index, element) {
        $(element).slider({
            value: 1,
            min: 0,
            max: 2,
            step: 1,
            slide: function (evt, ui) {
                labelSlider(evt.target, ui.value);
            }
        }).position({my: "center", at: "center center", of: $(element).parent()});
        labelSlider(element, 1);
    });

    $('#answercontainer').position(
        {my: 'top', at: 'bottom', of: $('#letters')}
    );

    $('body').keydown(function(event) {
         if(typeIn.enabled) {
            if(event.which == 13 || event.which == 32) {
                event.preventDefault();
                typeIn.submit();
            } else if (event.which == 8 || event.which == 46) {
                typeIn.clear();
                event.preventDefault();
            } else if (true){
                // check for legal, perhaps alert if not
                var chara = String.fromCharCode(event.which).toLowerCase();
                if(Match.current.letterSet.indexOf(chara) > -1) {
                    typeIn(chara);
                } else {
                    // nothing, I guess...
                    return;
                }
            }
        } else {
            // not enabled
            return;
        }
    });
};

$(init);

// End of game logic

//TAG:AUTH

function getPicture(player, size) { // a nice utility function for FB users
    if(!size) size = 'small';
    if(auth) {
        return '//graph.facebook.com/' + player + '/picture?type=' + size;
    }
}

// Get a player's name, doing something fancier if FB data is available
function getName(player) {
    if(!player) {
        getName.nameOf = {}; // clear cache if passed FALSE
        getName.nameOf[settings.uid] = auth ? settings.profile.first_name : settings.uid;
    }
    if (auth && (typeof(FB) === 'undefined' || !FB.getAccessToken())) {
        // work around a state where FB is defined but FB.gAT = null
        setTimeout(getName.bind(undefined, false), 10);
        // handle the common case gracefully
        return player==settings.uid ? settings.profile.first_name : player;
    }
    if(getName.nameOf && getName.nameOf[player]) return(getName.nameOf[player]);

    // If FB is available, read all friends' names from the Graph API
    // and refresh the cache
    else if(auth && typeof(FB) !== 'undefined' && !getName.lock) {
        getName.lock = true;
        FB.api('/me/friends?fields=first_name,id',
               'GET', function(x) {
                   for (i in x.data)
                       getName.nameOf[x.data[i].id] = x.data[i].first_name;
               }
              );
    }
    return player;
}

$(getName.bind(undefined, false));
///TAG:AUTH

//TAG:REQUESTS
// Gate the requests functionality
$(function() {
    if(!requests) return;
    var requestsButton = $(
        '<div />',
        {id: 'rqButton', text: 'Inbox', class:'jqbutton'}
    );
    $(requestsButton).button().click(manageRequests);
    $('#topcontainer').append(requestsButton);
    var inviteButton = $('<div />', {id: 'invButton', text: 'Invite friends!'});
    $(inviteButton).button().click(inviteFriends);
    $('#topcontainer').append(inviteButton);
});

// Display the requests dialog
function inviteFriends() {
    if(!requests) return;
    if(inviteFriends.lock) return;
    inviteFriends.lock = true;
    FB.ui ({ // FB.ui() invokes all JS SDK dialogs
        method: 'apprequests', // method controls which box to show
        message: 'A challenge awaits you in AGARMAN!', // request text
    }, rqCallback);
}

// Create a dialog to manage your requests
function manageRequests() {
    if(manageRequests.lock) return; // only display one of these at a time
    manageRequests.lock = true;
    var requests = settings['appRequests']; // the server preloads requests at init

    if($('#rqdialog')) $('#rqdialog').remove();
    manageRequests.dialog = $('<div style="display:none" />', {id: 'rqdialog'});
    if(!requests || !requests.length) { // no requests waiting?
        $(manageRequests.dialog).append(
            $('<div />', {text: 'You have no pending requests.'})
        );
    } else {
        for (i in requests) { // build an HTML widget to display/manage each one
            var rq = requests[i];
            var entry =
                $("<div />", {id: rq.id, class:'requestContainer container'});
            $(entry).append($("<img />", {src: ("//graph.facebook.com/"
                                                + rq.from.id +
                                                '/picture?type=small'),
                                          class: 'frompic'}));
            $(entry).append($("<div />", {class: 'messagetext', text:rq.message}));
            $(entry).append($("<div />", {class: 'acceptButton', text: 'Accept'})
                            .button().click(deleteRq.bind(undefined, rq.id, true)));
            $(entry).append($("<div />", {class: 'deleteButton', text: 'X'})
                            .button().click(deleteRq.bind(undefined, rq.id, false)));
            $(manageRequests.dialog).append(entry);
        }
    }
    $(manageRequests.dialog).dialog(
        { buttons: { 'Invite Friends...': inviteFriends,
                     "Close": function() { $(this).dialog("close"); }
                   },
          autoOpen: true,
          title: 'Manage requests',
        }
    ).bind("dialogclose", function() {manageRequests.lock=false; updateRq()});
                                            // refresh on close  ^^^^^^^^^^
}

// Delete a request, perhaps handling it differently if it was accepted/rejected
function deleteRq(id, accepted) {
    $('#'+id).hide('blind');
    if(accepted) {
        // handle whatever needs to be different if the request was accepted
    }
    FB.api('/' + id, 'DELETE'); // Use the Graph API to delete the request
    delete(settings['requests']['id']);
}

function updateRq(continuing, data) {
    if(!continuing) {
        FB.api('/me/apprequests', 'GET', updateRq.bind(undefined, true));
        // ^^ Use the Graph API to read pending requests
        return;
    }
    settings['requests'] = data || []; // make sure it's an empty array on failure
}

// A useful diagnostic callback
function rqCallback(data) {
    inviteFriends.lock = false;
    if(console && console.log) {
        console.log(data);
    }
}
///TAG:REQUESTS

//TAG:FEED
// Pop up the feed dialog, allowing the user to claim a cool word for a
// superhero identity
function brag(message, substituands, continuing) {
    if(brag.lock && !continuing) return;
    if(continuing) {
        brag.lock=false;
        console.log(message);
        return;
    }
    brag.lock=true;
    var msg = $.extend({}, brag.stories[message]);

    if (msg.data) {
        for (i in msg.data) {
            if(!(substituands[i] || i == 'data')) substituands[i] = msg.data[i];
        }
    }
    delete(msg.data);
    for (k in substituands) {
	var v = substituands[k];
        for (field in msg) {
            msg[field] = msg[field].replace(new RegExp(k, 'g'), v);
        }
    }
    msg.method = 'feed';
    msg.link = 'http://apps.facebook.com/' + settings['appName']
        + '?feedcopy=' + message;
    if(feed) FB.ui(msg, function(x) {brag(x, false, true);});
}

// Some data to dynamically compute fun stories for the feed
brag.stories = {
    word: {
        name: 'My superheroic identity: WORDTYPE!',
        caption: "But it's a secret, so keep it to yourself",
        description: 'I found the word "WORD" ' +
            'hiding in the letters LETTERS ' +
            'and scored POINTS points in AGAR MAN, ' +
            'the game of superheroic word-finding!',
        picture: 'http://freeeel.com/nagaram/client/agarthumb.png',
        data: {
            TYPE: function() {
                var types = {
                    male: [' Man', ' Lad'],
                    female: [' Woman', ' Lass'],
                    epicene: ['-o-tron', 'iac']
                };
                var available = types.epicene;
                if(settings.profile && settings.profile.gender) {
                    available = available.concat(types[settings.profile.gender]);
                }
                return available[Math.floor(Math.random() * available.length)];
            },
        }
    },
}

//TAG:SCORES
// Get the leaderboard data
function popLeaders() {
    var count = arguments[0] || 3;
    var scores = FB.api('/' + settings['appID'] + '/scores',
                        renderLeaders.bind(undefined, count)
                       );
}

// Render the leaderboard of the users' friends, up to COUNT
function renderLeaders(count, response) {
    if(renderLeaders.lock) return;
    renderLeaders.lock = true;
    var box = $('<div id="highscorecontainer" class="container" />');
    for(var x in response.data) {
        if(x>=count) break;
        var i = response.data[x];
        var entry = $(
            '<div class="scoreentry container">'+
                ' <img class="scorepic" src="//graph.facebook.com/' +
                i.user.id + '/picture?type=normal";" />' +
                ' <div class="scorename">' + i.user.name + '</div>' +
                ' <div class="scorenumber">' + i.score + '</div></div>');
        $(box).append(entry);
    }
    $(box).dialog(
        { buttons: { "Close": function() { $(this).dialog("close"); }
                   },
          autoOpen: true,
          title: "Your friends' best scores",
        }
    ).bind("dialogclose", function() {renderLeaders.lock=false;});
}

//TAG:PAYMENTS
// Create the dialog to buy an item
function payDialog(item) {
    FB.ui( // used to invoke all JS SDK dialogs
        {
            method: 'pay',      // / together, these determine which
            action: 'buy_item', // \ dialog to display

            order_info: {item_id: item}, // can be any value - FB passes this
                                         // through to the callback, treating it
                                         // as opaque
            dev_purchase_params: {'oscif': true}, // to display prices in the
                                                  // user's preferred currency
        },
        updateBalance
    );
}

// Get an updated balance of premium currency
function updateBalance() {
    $.get('?f=b&'+settings.sr, function(x) {
        var baldiv = $('#moneysbalance');
        if (0 == baldiv.length) {
            baldiv = $('<div />', {
                id: 'moneysbalance'
            }).insertBefore($('#purchasecontainer'));
        }
        settings.balance = x;
        baldiv.text('Balance: ' + settings.balance).
            append($('<img />', {src: 'og/bucks.png', class: 'moneysicon'}));
        $('#purchasecontainer').position({'my': 'top', 'at':'bottom',
                                          'of':$('#startcontainer')});
    });
}

// Gate the payment functionality
function initBalance() {
    if(!payments) return;
    $('#purchasecontainer').show();
    if (auth && (typeof(FB) === 'undefined' || !FB.getAccessToken())) {
        setTimeout(initBalance, 10);
        return;
    }

    FB.api('/me?fields=currency', convertDisplayPrices);
    updateBalance();
};

$(initBalance);

function convertDisplayPrices(currencyData) {
    var currmap = {
        USD: '$',
        EUR: '€', //...and any other currencies relevant to your users
    }
    var currency = currmap[currencyData.currency.user_currency]
        || currencyData.currency.user_currency, // try to map it, OK on fail
    rate = currencyData.currency.currency_exchange_inverse,
    offset = currencyData.currency.currency_offset,
    offsetDigits = {1:0, 10:-1, 100:-2, 1000: -3}[offset];

    // JS's round only rounds to int, so we'll tweak the basic exchange
    // algorithm to be, essentially:
    // localPrice = round(credsPrice * rate * offset) / offset
    for(var item in settings.catalog) {
        price = settings.catalog[item].price;
        var localPrice = String(Math.round(price * rate * offset));
        var minorUnits = localPrice.substr(offsetDigits);
        var majorUnits = localPrice.substring(0, localPrice.length + offsetDigits)
            || "0";
        var separator = (1.1).toLocaleString()[1]; // ,/. as decimal separator
        var displayPrice = currency + String(majorUnits) +
            (minorUnits ? separator + minorUnits : '');
        settings.catalog[item].displayPrice = displayPrice;
    }

    $('#purchasedialog').remove();

    var root = $('<div />', {id: 'purchasedialog'}).
                 css({'display': 'hidden'}).appendTo($('#purchasecontainer'));

    for(var item in settings.catalog) { // build a simple storefront
        $('<div />', {
            class: 'jqbutton',
            id: 'buybutton',
            html: 'Buy ' + settings.catalog[item].title  +
                ' - just <span id="itemprice">' +
                settings.catalog[item].displayPrice +
                '</span>'
        }).button().click(payDialog.bind(undefined, item)).appendTo(root);
    }

    root.dialog({autoOpen: false, title: 'Buy Agar Bucks', width: 385});

    var getButton = $('#getbutton').length ||
        $('<div />', {id: 'getbutton', text: 'Buy Agar Bucks'}).
          button().
          click(root.dialog.bind(root, 'open')).
          appendTo($('#purchasecontainer'));
    $('#purchasecontainer').position({'my': 'top', 'at':'bottom',
                                      'of':$('#startcontainer')});

}

//TAG:OFFERS
// Create the Trialpay offer wall dialog
function offerWall() {
    FB.ui(
        {
            method: 'pay',           // / Which dialog
            action: 'earn_currency', // \  to display
            product: 'http://freeeel.com/nagaram/og/bucks.html', // OG URL
        },
        updateBalance
    );
}

// Pop up the Trialpay payer promotion offer (free money - do this!)
function ppOffer() {
    FB.ui(
        {
            method: 'fbpromotion', // / Together, these determine which dialog
            display: 'popup',      // \ is displayed
            package_name: 'zero_promo', // Which promotion to offer
            product: 'http://apps.facebook.com/agarman/og/bucks.html' // OG URL
        },
        updateBalance
    );
}

// Gate the offer functionality - call in fbAsyncInit
function initOffer() {
    if(!offers) return false;
    if(typeof(FB) == 'undefined' || !FB.getAccessToken()) {
        window.setTimeout(initOffer, (arguments[0] * 2) || 2);
        return;
    } // workaround in case fbAsyncInit fires with no access token available

    var root = $('#purchasecontainer');

    // Check eligibility for Payer Promo and display if relevant
    FB.api('/me/?fields=is_eligible_promo', 'GET', function (x) {
        if(x.is_eligible_promo && x.is_eligible_promo == 1) {
            $('<div />', {id: 'promobutton', text: 'FREE Agar Bucks'}).button().
                click(ppOffer).appendTo(root); //ppoffer is defined above
        } else {
            $('<div />', {id: 'wallbutton', class: 'jqbutton', text: 'Earn Agar Bucks'}).button().
                click(offerWall).appendTo(root);
        }
        $('#purchasecontainer').position({'my': 'top', 'at':'bottom',
                                          'of':$('#startcontainer')});
    });
}
