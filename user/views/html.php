<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.1//EN" "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-2.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
	<head>

		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />

		<title><%= $this->rcombine('title') %></title>

		<!--[if IE]><![endif]-->

		<% $this->place('styles');  %>
		<% $this->place('scripts'); %>

	</head>

	<body id="<%= $this['id'] %>" class="<%= $this->combine('classes', ' ') %>">

		<% $this->place('header'); %>

		<% if ($this->check('error')) { %>
			<div class="error">
				<%= $this['error'] %>
			</div>
		<% } %>

		<% $this->place('contents'); %>

		<% $this->place('footer'); %>

	</body>
</html>
