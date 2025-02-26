// Shows the main menu
// The dispaly css setting is inline html not in the css file

function showMenu() {    
    // select the menu
    var x = document.getElementById("dropDownSection");
    // select the hamburger btn
    var y = document.getElementById("menu-btn");

    if (x.style.display === "none") {
        // show the dropdown menu
      x.style.display = "block";
      //transition hamburger to x
      y.classList.add("is-active");
    } else {
      x.style.display = "none";
      y.classList.remove("is-active");
    }
  }


//  sub menu icon and color toggler + -

$(".fa-plus").click(function(){
  // switch icons + to -
    $(this).toggleClass("fa-minus");
    // Switches the button's bg color to red
    $(this).parent().toggleClass("red background");    
  });



// This closes and shows the modal
  // on preview show iframe
    $('#videoModal').on('show.bs.modal', function (e) {
      var idVideo = $(e.relatedTarget).data('id');
      $('#videoModal .modal-body').html('<iframe src="https://player.vimeo.com/video/341581667" frameborder="0" allowfullscreen></iframe>');
    });
    //on close remove
    $('#videoModal').on('hidden.bs.modal', function () {
       $('#videoModal .modal-body').empty();
    });



//Offset padding
var paddingValue = $('#nav-container').innerHeight();
var paddingValue2 = $('#nav-container').innerHeight();

$('#carousel').css("paddingTop", paddingValue)
$('#jumbotron').css("paddingTop", paddingValue2 + 40)
