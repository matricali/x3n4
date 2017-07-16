function X3N4 () {
  this.version = window.x3n4_version || 'Custom version'
  this.script_path = window.script_path || '/x3n4.php'
  this.directory_separator = window.directory_separator || '/'

  this.safe_tags = function (str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  }

  this.execCommand = function (command) {
    if (command.trim() === 'clear') {
      $('#stdout').html('')
      return
    }

    $.post(this.script_path, {cmd: command}, function (data) {
      console.log(data)
      if (data.stdout) {
        $('#stdout').append(window.x3n4.safe_tags(data.banner + ' ' + command + '\n'))
        if (data.stdout !== null) {
          $('#stdout').append(window.x3n4.safe_tags(data.stdout))
        }
        $('#pwd').html(window.x3n4.safe_tags(data.banner))
      } else {
        $('#stdout').append(window.x3n4.safe_tags(data))
      }
      $('#stdout').scrollTop($('#stdout')[0].scrollHeight)
    })
  }

  this.evalPhp = function (code) {
    var evalt1 = Date.now()
    $.post(this.script_path, {eval: code}, function (data) {
      var evaltime = Date.now() - evalt1
      console.log(data)
      if (data.stdout) {
        $('#php-stdout').html(data.stdout)
      } else {
        $('#php-stdout').html(data)
      }
      if (data.took !== undefined) {
        $('#eval-time-took').html('Request time: ' + evaltime + 'ms. PHP process time: ' + data.took + 'ms.')
      } else {
        $('#eval-time-took').html('Request time: ' + evaltime + 'ms.')
      }
    })
  }

  this.clickExecCommand = function () {
    window.x3n4.execCommand($('#stdin').val())
    $('#stdin').val('')
  }

  this.clickEval = function () {
    var code = ''
    if (window.editorPhp) {
      code = window.editorPhp.getValue()
    } else {
      code = $('#php-code').val()
    }
    if (code !== undefined) {
      window.x3n4.evalPhp(code)
    }
  }

  this.checkUpdate = function () {
    $.get('https://api.github.com/repos/jorge-matricali/x3n4/releases', function (data) {
      if (window.x3n4.version !== data[0].tag_name) {
        $('#stdout').append('/!\\ x3n4 ' + data[0].tag_name + " available. Type 'upgrade' to download the latest version automatically.\n")
        $('#stdout').scrollTop($('#stdout')[0].scrollHeight)
      }
    })
  }

  this.declareCallbacks = function () {
    $('#btnExecCommand').on('click', this.clickExecCommand)
    $('#btnEval').on('click', this.clickEval)
    $('#stdin').on('keypress', function (ev) {
      if ((ev.keyCode ? ev.keyCode : ev.which) === 13) {
        $('#btnExecCommand').click()
      }
    })
    $('a[href="#console"]').on('click', function (ev) {
      window.setTimeout(function () {
        $('#stdin').focus()
      }, 200)
    })
  }

  this.fileManagerPathLinks = function (path) {
    var html = ''
    var cpath = ''
    $.each(path.split(window.x3n4.directory_separator), function (i, el) {
      if (el === '') return
      cpath += window.x3n4.directory_separator + el
      html += window.x3n4.directory_separator + '<a href="#file-manager" onclick="window.x3n4.fileManagerGetDirectory(\'' + cpath + '\')" title="' + cpath + '">' + el + '</a>'
    })
    return html
  }

  this.fileManagerGetDirectory = function (path) {
    $.get(this.script_path + '?dir=' + path, function (data) {
      window.x3n4.fileManagerRenderDirectory(data)
    })
  }

  this.fileManagerGetFile = function (path) {
    $('#file-manager').html('<p><i class="fa fa-chevron-right"></i> ' + window.x3n4.fileManagerPathLinks(path) + '</p>')
    $.get(this.script_path + '?file=' + path, function (data) {
      $('#file-manager').append($('<pre>' + data.content + '</pre>'))
    })
  }

  this.fileManagerRenderDirectory = function (data) {
    var html = '<p><i class="fa fa-chevron-right"></i> ' + window.x3n4.fileManagerPathLinks(data.path) + '</p>'
    html += '<ul class="list-group"><li class="list-group-item">..</li>'
    if (data.files) {
      $.each(data.files, function (i, el) {
        if (el.filename !== '.' && el.filename !== '..') {
          html += '<li class="list-group-item"><span class="col-sm-4">'
          if (el.type === 'folder') {
            html += '<i class="glyphicon glyphicon-folder-close"></i> '
            html += '<a href="#file-manager" onclick="window.x3n4.fileManagerGetDirectory(\'' + el.fullpath + '\')" title="' + el.filename + '">' + el.filename + '</a>'
          } else {
            html += '<i class="glyphicon glyphicon-file"></i> '
            html += '<a href="#file-manager" onclick="window.x3n4.fileManagerGetFile(\'' + el.fullpath + '\')" title="' + el.filename + '">' + el.filename + '</a>'
          }

          html += '</span><!--span class="col-sm-2"-->'
          // ---
          html += '<span class="clearfix"></span>'
          html += '</li>'
        }
      })
    }
    html += '</ul>'
    $('#file-manager').html(html)
  }
}
window.x3n4 = new X3N4()
window.x3n4.declareCallbacks()
window.x3n4.checkUpdate()
