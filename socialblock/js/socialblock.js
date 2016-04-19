window.onload = function () {
    const TOPIC_MAX_LINES = 5;
    const CONTAINER_CLASS = '.socialblock.container';
    const TITLE_CLASS = '.socialblock.title';
    const TITLES = document.querySelectorAll(TITLE_CLASS);
    const REM = getRem();

    for (var i = 0; i < TITLES.length; i++) {
        var borderWidth = parseFloat(getComputedStyle(TITLES[i]).getPropertyValue('border-width'));
        var titleHeight = parseFloat(getComputedStyle(TITLES[i]).getPropertyValue('height')) - borderWidth * 2;
        var lineHeight = getLineHeight(TITLE_CLASS, CONTAINER_CLASS);
        var numberOfLines = titleHeight / lineHeight;
        if (numberOfLines > TOPIC_MAX_LINES) {
            TITLES[i].style.height = lineHeight * TOPIC_MAX_LINES + borderWidth * 2 + 'px';
            TITLES[i].style.overflow = 'hidden';
        }
    }

    var cards = document.querySelectorAll('.socialblock.card.tweet');
    for (var j = 0; j < cards.length; j++) {
        if (cards[j].children.length > 2) {
            cards[j].children[0].style.border = 'none';
        }
    }

    var container = document.querySelectorAll(CONTAINER_CLASS);
    for (var i = 0; i < container.length; i++) {
        var msnry = new Masonry(container[i], {
            gutter: 1.25 * REM,
            fitWidth: true
        });
    }
};

/**
 * Returns the size of 1 rem in px
 */
function getRem() {
    var dummyDiv = document.createElement('div');
    dummyDiv.style.cssText = 'font-size:1rem;visibility:hidden';
    document.body.appendChild(dummyDiv);
    var out = parseFloat(getComputedStyle(dummyDiv).getPropertyValue('font-size'));
    document.body.removeChild(dummyDiv);
    return out;
}

/**
 * Returns an element's line height in px
 * @param {string} c - the element class
 * @param {string} p - the parent class
 */
function getLineHeight(c, p) {
    var fontFamily = getComputedStyle(document.querySelector(c)).getPropertyValue('font-family');
    var actualFontSize = parseFloat(getComputedStyle(document.querySelector(c)).getPropertyValue('font-size')); //font-size in px
    var cssFontSize = actualFontSize / getRem();                                                                //css-defined font-size in rem
    var cssLineHeight = getComputedStyle(document.querySelector(c)).getPropertyValue('line-height');            //css-defined line-height in rem
    var dummyDiv = document.createElement('div');
    dummyDiv.appendChild(document.createTextNode('blah'));
    dummyDiv.style.cssText = 'font-family:' + fontFamily + ';font-size:' + cssFontSize + 'rem;line-height:' + cssLineHeight + ';visibility:hidden';
    document.querySelector(p).appendChild(dummyDiv);
    var actualLineHeight = parseFloat(getComputedStyle(dummyDiv).getPropertyValue('height'));
    document.querySelector(p).removeChild(dummyDiv);
    return actualLineHeight;
}