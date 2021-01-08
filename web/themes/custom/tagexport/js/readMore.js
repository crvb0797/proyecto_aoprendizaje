function readMore() {
  let moreText = document.getElementById("more");
  let btnText = document.getElementById("btn");

  if (moreText.style.display === "inline") {
    btnText.innerHTML = "Leer Más...";
    moreText.style.display = "none";
  } else {
    btnText.innerHTML = "Leer Menos";
    moreText.style.display = "inline";
  }
}
