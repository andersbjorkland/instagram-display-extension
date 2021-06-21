const container = document.getElementById('instagram-media-container');
const previousControl = document.getElementById('instagram-media-paging-previous');
const nextControl = document.getElementById('instagram-media-paging-next');
const followContainer = document.getElementById('instagram-media-follow');
const followLink = document.getElementById('instagram-media-follow-link');
const wrapper = document.getElementById('instagram-media-wrapper');
const newTabIcon = document.getElementsByClassName('andersbjorkland-icon')[0];

const getVideoElement = (video, width = -1, height = -1) => {
    let source;

    if (video.filepath?.length > 0) {
        source = video["filepath"];
    } else {
        source = video["instagram_url"];
    }

    const videoElement = document.createElement("video");
    videoElement.setAttribute("src", source);
    videoElement.setAttribute("controls", "controls");

    if (width > 0) {
        videoElement.setAttribute("width", "" + width);
    }

    if (height > 0) {6
        videoElement.setAttribute("height", "" + height);
    }

    return videoElement;
}

const getImageElement = (image) => {
    let source;

    if (image.thumbnail.length > 0) {
        source = image["thumbnail"];
    } else {
        source = image["instagram_url"];
    }

    const imageElement = document.createElement("img");
    imageElement.setAttribute("src", source);
    imageElement.setAttribute("alt", "");

    return imageElement;
}

const createElementsFromData = (data) => {
    let videoWidth = -1;
    let videoHeight = -1;
    let linkBgColor = null;

    if ("video_width" in data) {
        videoWidth = data["video_width"];
    }

    if ("video_height" in data) {
        videoHeight = data["video_height"];
    }

    if ("overlay_color" in data) {
        linkBgColor = data["overlay_color"];
    }

    if ("media" in data) {
        if (Array.isArray(data.media)) {
            data.media.forEach(item => {
                if (Object.keys(item).includes("media_type")) {
                    let elementToAppend = null;
                    switch (item["media_type"]) {
                        case "VIDEO":
                            elementToAppend = getVideoElement(item, videoWidth, videoHeight);
                            break;

                        case "IMAGE":
                            elementToAppend = getImageElement(item);
                            break;

                        case "CAROUSEL_ALBUM":
                            elementToAppend = getImageElement(item);
                            break;

                        default:
                            console.log("UNKNOWN MEDIA TYPE: " + item["media_type"]);
                    }

                    if (elementToAppend !== null) {
                        const elementWrapper = document.createElement("div");
                        const elementLinkWrapper = document.createElement("div");
                        const elementLink = document.createElement("a");
                        const elementNewTabIcon = newTabIcon.cloneNode(true);

                        elementWrapper.classList.add("element-wrapper");
                        elementLinkWrapper.classList.add("element-link-wrapper");

                        if (linkBgColor !== null) {
                            elementLink.setAttribute("style", "background-color: " + linkBgColor + ";");
                        }

                        elementLink.setAttribute("href", item["permalink"]);
                        elementLink.setAttribute("target", "__hidden");
                        elementLink.setAttribute("rel", "noopener");
                        elementLink.appendChild(elementNewTabIcon);
                        elementLinkWrapper.appendChild(elementLink);
                        elementWrapper.appendChild(elementToAppend);
                        elementWrapper.appendChild(elementLinkWrapper);
                        container.appendChild(elementWrapper);
                    }

                }

            });
        }
    }
}

const managePagingControls = (data) => {
    if ("paging" in data) {
        if ("previous" in data.paging) {
            previousControl.classList.remove("hidden");
            previousControl.dataset.previous = data.paging.previous;

        } else {
            previousControl.classList.add("hidden");
            previousControl.dataset.previous = "";
        }

        if ("next" in data.paging) {
            nextControl.classList.remove("hidden");
            nextControl.dataset.next = data.paging.next;
        } else {
            nextControl.classList.add("hidden");
            nextControl.dataset.next = "";
        }
    }
}

const manageFollowLink = (data) => {
    let shouldShow = false;
    let url = "";
    let followClassnames = "";

    if ("instagram_follow" in data) {
        if ("url" in data["instagram_follow"]) {
            shouldShow = true;
            url = data["instagram_follow"]["url"];
        }

        if ("classname" in data["instagram_follow"]) {
            followClassnames = data["instagram_follow"]["classname"];
            
            if (followClassnames.length > 0){
                followClassnames = followClassnames.split(" ");
                followLink.classList.add(...followClassnames);
            }
        }
    }
    if (shouldShow) {
        followContainer.classList.remove("hidden");
        followLink.setAttribute("href", url);
    } else {
        followContainer.classList.add("hidden");
    }
}

const manageWrapper = (data) => {
    if ("icon_color" in data) {
        let color = data["icon_color"];
        wrapper.setAttribute("style", "--fill-color:" + color + " ; --stroke-color: " + color + ";");
    }

    if ("default_style" in data) {
        let useDefaultStyle = data["default_style"];
        if (!useDefaultStyle) {
            wrapper.classList.remove("default-style");
        }
    }
}

const clearMedia = () => {

    // As describe in option 2 A: https://stackoverflow.com/questions/3955229/remove-all-child-elements-of-a-dom-node-in-javascript
    while (container.firstChild) {
        container.removeChild(container.lastChild);
    }
}

const updateMedia = (query = "") => {

    return fetch('/extensions/instagram-display/media/async' + query)
        .then(result => {
            clearMedia();
            return result.json()
        })
        .then(data => {
            createElementsFromData(data);
            manageWrapper(data);
            managePagingControls(data);
            manageFollowLink(data);
        });
}



const handlePreviousButton = () => {
    const direction = "before";
    const cursor = previousControl.dataset.previous;
    const query = "?direction=" + direction +"&cursor=" + cursor;

    previousControl.disabled = true;
    previousControl.classList.add("loading");
    let loading = updateMedia(query);

    loading.then(() => {
        previousControl.classList.remove("loading");
        previousControl.disabled = false;
    });
}

const handleNextButton = () => {
    const direction = "after";
    const cursor = nextControl.dataset.next;
    const query = "?direction=" + direction +"&cursor=" + cursor;

    nextControl.disabled = true;
    nextControl.classList.add("loading");
    let loading = updateMedia(query);

    loading.then(() => {
        nextControl.classList.remove("loading");
        nextControl.disabled = false;
    });
}

updateMedia();